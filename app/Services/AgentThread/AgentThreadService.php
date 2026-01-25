<?php

namespace App\Services\AgentThread;

use App\Api\AgentApiContracts\AgentCompletionResponseContract;
use App\Api\Options\ResponsesApiOptions;
use App\Jobs\ExecuteThreadRunJob;
use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadMessage;
use App\Models\Agent\AgentThreadRun;
use App\Models\Agent\McpServer;
use App\Models\Schema\SchemaDefinition;
use App\Models\Schema\SchemaFragment;
use App\Services\JsonSchema\JsonSchemaService;
use App\Services\Usage\UsageTrackingService;
use Newms87\Danx\Traits\HasDebugLogging;
use Exception;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\LockHelper;
use Newms87\Danx\Jobs\Job;
use Throwable;

class AgentThreadService
{
    use HasDebugLogging;

    protected ?SchemaDefinition $responseSchema    = null;

    protected ?SchemaFragment $responseFragment  = null;

    protected ?JsonSchemaService $jsonSchemaService = null;

    protected ?McpServer $mcpServer         = null;

    protected ?int $timeout           = null;

    protected int $currentTotalRetries = 0;

    protected ?int $currentApiLogId = null;

    /**
     * Overrides the response format for the thread run.
     * This will replace the Agent's response format with the provided schema and fragment
     */
    public function withResponseFormat(?SchemaDefinition $responseSchema = null, ?SchemaFragment $responseFragment = null, ?JsonSchemaService $jsonSchemaService = null): static
    {
        $this->responseSchema    = $responseSchema;
        $this->responseFragment  = $responseFragment;
        $this->jsonSchemaService = $jsonSchemaService;

        return $this;
    }

    /**
     * Set the MCP server to be used for this thread run
     */
    public function withMcpServer(?McpServer $mcpServer = null): static
    {
        $this->mcpServer = $mcpServer;

        return $this;
    }

    /**
     * Set the timeout in seconds for this thread run
     */
    public function withTimeout(?int $timeout = null): static
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Creates an agent thread run based on the defined parameters configured for the service
     */
    public function prepareAgentThreadRun(AgentThread $agentThread): AgentThreadRun
    {
        LockHelper::acquire($agentThread);

        try {
            if ($agentThread->isRunning()) {
                throw new ValidationError("Thread is already running: $agentThread");
            }

            $agent = $agentThread->agent;

            $agentThreadRun = $agentThread->runs()->make([
                'agent_model'          => $agent->model,
                'status'               => AgentThreadRun::STATUS_RUNNING,
                'api_options'          => $agent->api_options,
                'response_format'      => $this->responseSchema ? 'json_schema' : 'text',
                'response_schema_id'   => $this->responseSchema?->id,
                'response_fragment_id' => $this->responseFragment?->id,
                'json_schema_config'   => $this->jsonSchemaService?->getConfig(),
                'mcp_server_id'        => $this->mcpServer?->id,
                'timeout'              => $this->timeout,
            ]);

            // Save a snapshot of the resolved JSON Schema to use as the agent's response schema,
            // and so we can clearly see what the schema was at the time of running the request
            if ($this->responseSchema) {
                if (!$this->responseSchema->schema) {
                    throw new ValidationError('Response schema has no schema defined: ' . $this->responseSchema);
                }
                $agentThreadRun->response_json_schema = $agentThreadRun->renderResponseJsonSchema($this->responseSchema->name, $this->responseSchema->schema, $this->responseFragment?->fragment_selector);
            }

            $agentThreadRun->save();
        } finally {
            LockHelper::release($agentThread);
        }

        return $agentThreadRun;
    }

    /**
     * Dispatch an agent thread run to run asynchronously in a job. Returns the pending agent thread run.
     */
    public function dispatch(AgentThread $agentThread): AgentThreadRun
    {
        $agentThreadRun = $this->prepareAgentThreadRun($agentThread);

        // Execute the thread run in a job
        $job                             = (new ExecuteThreadRunJob($agentThreadRun))->dispatch();
        $agentThreadRun->job_dispatch_id = $job->getJobDispatch()?->id;
        $agentThreadRun->save();

        return $agentThreadRun;
    }

    /**
     * Run an agent thread run immediately and return the completed agent thread run
     */
    public function run(AgentThread $agentThread): AgentThreadRun
    {
        $agentThreadRun = $this->prepareAgentThreadRun($agentThread);

        // If we are currently in a running job, attach the job dispatch ID to the thread run
        if (Job::$runningJob) {
            $agentThreadRun->job_dispatch_id = Job::$runningJob->id;
            $agentThreadRun->save();
        }

        $this->executeThreadRun($agentThreadRun);

        return $agentThreadRun->fresh();
    }

    /**
     * Stop the currently running thread (if it is running)
     */
    public function stop(AgentThread $thread): ?AgentThreadRun
    {
        LockHelper::acquire($thread);
        $threadRun = $thread->currentRun;
        if ($threadRun) {
            $threadRun->stopped_at = now();
            $threadRun->save();
        }
        LockHelper::release($thread);

        return $threadRun;
    }

    /**
     * Resume the previously stopped thread (if there was a stopped thread run)
     */
    public function resume(AgentThread $agentThread): ?AgentThreadRun
    {
        LockHelper::acquire($agentThread);
        $agentThreadRun = $agentThread->runs()->where('status', AgentThreadRun::STATUS_STOPPED)->latest()->first();

        if ($agentThreadRun) {
            $agentThreadRun->stopped_at   = null;
            $agentThreadRun->completed_at = null;
            $agentThreadRun->failed_at    = null;
            $agentThreadRun->started_at   = null;

            $agentThreadRun->job_dispatch_id = (new ExecuteThreadRunJob($agentThreadRun))->dispatch()->getJobDispatch()?->id;
            $agentThreadRun->save();
        }
        LockHelper::release($agentThread);

        return $agentThreadRun;
    }

    /**
     * Execute the thread run to completion
     */
    public function executeThreadRun(AgentThreadRun $agentThreadRun): void
    {
        try {
            // Reset retry counter for new thread run
            $this->currentTotalRetries = 0;

            LockHelper::acquire($agentThreadRun->agentThread);
            LockHelper::acquire($agentThreadRun);

            $agentThreadRun->started_at = now();
            $agentThreadRun->save();

            static::logDebug("Executing $agentThreadRun");

            $agentThread = $agentThreadRun->agentThread;
            $agent       = $agentThread->agent;

            $retries = $agent->retry_count ?: 0;

            static::logDebug("executeThreadRun start - retries={$retries} threadRun={$agentThreadRun->id}");

            // Create exception handler for this thread run
            $exceptionHandler = new AgentThreadExceptionHandler();

            do {
                $agentThreadRun->refresh();
                if (!$agentThreadRun->isStatusRunning()) {
                    static::logDebug("$agentThreadRun is no longer running: " . $agentThreadRun->status);
                    break;
                }

                try {
                    // Always use Responses API (completions API removed)
                    $response = $this->executeResponsesApi($agentThreadRun);

                    if ($response->isFinished()) {
                        $this->handleResponse($agentThread, $agentThreadRun, $response);
                        break;
                    }

                    if ($response->isMessageEmpty()) {
                        throw new Exception('Empty response from AI model', 580);
                    }

                    throw new Exception('Response from AI model is not finished: ' . json_encode($response->getContent()), 581);
                } catch (Throwable $exception) {
                    $shouldRetry = $exceptionHandler->shouldRetry($exception);
                    static::logDebug("Exception caught - class=" . get_class($exception) . " code={$exception->getCode()} message=\"{$exception->getMessage()}\" shouldRetry=" . ($shouldRetry ? 'true' : 'false') . " threadRun={$agentThreadRun->id}");

                    // Handle all exceptions through centralized retry logic
                    if ($shouldRetry) {
                        continue;
                    }

                    // If we shouldn't retry, throw the exception
                    throw $exception;
                }
            } while ($retries-- >= 0);

            static::logDebug("Thread run loop exited - retries exhausted. threadRun={$agentThreadRun->id}");
        } catch (Throwable $throwable) {
            $agentThreadRun->failed_at = now();
            $agentThreadRun->save();
            throw $throwable;
        } finally {
            LockHelper::release($agentThreadRun);
            LockHelper::release($agentThreadRun->agentThread);
        }
    }

    /**
     * Get raw messages for API - handles both full and optimized message sets
     * Returns raw message objects that the API layer will format appropriately
     */
    public function getMessagesForApi(AgentThread $thread, AgentThreadRun $agentThreadRun): array
    {
        // Get only unsent messages (messages after last tracked response)
        $lastTrackedMessage = $thread->getLastTrackedMessageInThread();
        if ($lastTrackedMessage) {
            $messagesToSend = $thread->sortedMessages()
                ->where('id', '>', $lastTrackedMessage->id)
                ->get();
            static::logDebug('Using optimization: sending ' . count($messagesToSend) . " new messages after message {$lastTrackedMessage->id}");
        } else {
            // No tracked messages, send all messages
            $messagesToSend = $thread->sortedMessages()->get();
            static::logDebug('No tracked messages found, sending all ' . count($messagesToSend) . ' messages');
        }

        $messages = [];

        // Add raw message objects with metadata for API formatting
        foreach ($messagesToSend as $message) {
            $messageData = [
                'role'        => $message->role,
                'content'     => $message->summary ?: $message->content ?: '',
                'files'       => $message->storedFiles,
                'data'        => $message->data,
                'id'          => $message->id,
                'should_cite' => $agentThreadRun->getJsonSchemaService()->isUsingCitations() && $message->isUser(),
            ];
            $messages[]  = $messageData;
        }

        // Add response message instructions if needed
        $responseMessage = $this->getResponseMessage($agentThreadRun);
        if ($responseMessage) {
            $messages[] = [
                'role'        => AgentThreadMessage::ROLE_USER,
                'content'     => $responseMessage,
                'files'       => [],
                'data'        => null,
                'id'          => null,
                'should_cite' => false,
            ];
        }

        return $messages;
    }

    /**
     * Get the response message for the AI model
     */
    public function getResponseMessage(AgentThreadRun $agentThreadRun): string
    {
        $responseMessage = '';

        if ($agentThreadRun->response_format === AgentThreadRun::RESPONSE_FORMAT_JSON_SCHEMA && $agentThreadRun->getJsonSchemaService()->isUsingDbFields()) {
            $responseMessage .= <<<'STR'
Your response will be saved to the DB. In order to save correctly, the `name` attribute must be set as the unique identifier for the object type.

Your goal is to **investigate and determine the best value** for each attribute of every object in the response schema.

### **Rules for Assigning IDs:**
- If `json_content` contains an object with `type` and `id`, it represents an existing record in the DB.
- **Assign `id` ONLY if an object with a matching `type` is found in `json_content`.** The `type` field in `json_content` **must exactly match** the `title` field in the schema.
- **DO NOT** use an `id` from a different `type`. IDs are not interchangeable.
- If an object exists in the schema but is **not present in `json_content`**, set `id: null` to create a new record.
- Similar names (e.g., "Johnson and Johnson" vs. "Johnson & Johnson") should resolve to the same object to **avoid duplicate records**.

### **Attribute Handling:**
- **Only update an attribute** if you have a new or better value than what is already in `teamObjects`.
- New or better values may come from additional content sources (e.g., images, files, URLs leading to web pages, newly provided content).
- If no improvement is found, **leave the attribute value null** to avoid unnecessary updates.
- **NEVER make up values** for an attribute. If an attribute is not provided in the source content, set it to `null`.

By following these rules, ensure that data integrity is maintained, duplicate records are minimized, and unnecessary updates are avoided.
STR;

        }

        if ($agentThreadRun->response_format !== 'text') {
            $responseMessage .= "\n\nOUTPUT IN JSON FORMAT ONLY! NO OTHER TEXT";
        }

        return $responseMessage;
    }

    /**
     * Handle the response from the AI model
     */
    public function handleResponse(AgentThread $thread, AgentThreadRun $threadRun, AgentCompletionResponseContract $response): void
    {
        $newInputTokens  = $response->inputTokens();
        $newOutputTokens = $response->outputTokens();

        static::logDebug('Handling response from AI model. input: ' . $newInputTokens . ', output: ' . $newOutputTokens);

        $threadRun->update([
            'agent_model'  => $thread->agent->model,
            'refreshed_at' => now(),
        ]);

        // Record usage event for this specific AI call
        if ($newInputTokens > 0 || $newOutputTokens > 0) {
            // Calculate run time in milliseconds
            $runTimeMs = null;
            if ($threadRun->started_at) {
                $runTimeMs = (int)($threadRun->started_at->diffInMilliseconds(now()));
            }

            app(UsageTrackingService::class)->recordAiUsage(
                $threadRun,
                $thread->agent->model,
                [
                    'input_tokens'  => $newInputTokens,
                    'output_tokens' => $newOutputTokens,
                    'api_response'  => $response->toArray(),
                ],
                $runTimeMs
            );
        }

        $lastMessage = $thread->messages()->create([
            'role'       => AgentThreadMessage::ROLE_ASSISTANT,
            'content'    => $response->getContent(),
            'data'       => $response->getDataFields() ?: null,
            'api_log_id' => $this->currentApiLogId,
        ]);

        // Track the response ID for future optimization
        if ($response->getResponseId()) {
            $lastMessage->setApiResponseId($response->getResponseId());
            static::logDebug("Tracked response ID: {$lastMessage->api_response_id} for message {$lastMessage->id}");
        }

        // Clear the current API log ID after use
        $this->currentApiLogId = null;

        if ($response->isFinished()) {
            $this->finishThreadResponse($threadRun, $lastMessage);
        } else {
            throw new Exception('Unexpected response from AI model - response not finished');
        }
    }

    /**
     * Finish the thread response by updating the thread run
     */
    public function finishThreadResponse(AgentThreadRun $threadRun, AgentThreadMessage $lastMessage): void
    {
        static::logDebug('Finishing thread response...');

        $threadRun->update([
            'status'          => AgentThreadRun::STATUS_COMPLETED,
            'completed_at'    => now(),
            'last_message_id' => $lastMessage->id,
        ]);

        static::logDebug('AgentThread response is finished');
    }

    /**
     * Execute the thread run using the Responses API with response ID optimization
     */
    protected function executeResponsesApi(AgentThreadRun $agentThreadRun): AgentCompletionResponseContract
    {
        $agentThread = $agentThreadRun->agentThread;

        // Get API options from the thread run (source of truth) or use defaults
        $apiOptions = ResponsesApiOptions::fromArray($agentThreadRun->api_options ?? []);

        // Set timeout from the thread run for HTTP API calls
        if ($agentThreadRun->timeout) {
            $apiOptions->setTimeout($agentThreadRun->timeout);
        }

        // Build system instructions and always prepend them
        $apiOptions->addInstructions($this->buildSystemInstructions($agentThreadRun));

        // Add MCP server configuration if available
        $mcpServers = $this->getMcpServerConfiguration($agentThreadRun);
        if (!empty($mcpServers)) {
            $apiOptions->setMcpServers($mcpServers);
        }

        // Handle response format using Structured Outputs
        if ($agentThreadRun->response_format === AgentThreadRun::RESPONSE_FORMAT_JSON_SCHEMA) {
            $jsonSchema = $agentThreadRun->response_json_schema;

            if (!$jsonSchema) {
                throw new Exception('JSON Schema response format requires a schema to be set: ' . $agentThreadRun);
            }

            $apiOptions->setResponseJsonSchema($jsonSchema);
        } else {
            // Use default text format for non-JSON responses
            $apiOptions->setDefaultTextFormat();
        }

        // Handle message optimization with previous response ID
        // Use previous response ID for optimization
        $apiOptions->setPreviousResponseId($agentThread->getLastTrackedMessageInThread()?->api_response_id ?? null);
        $messages = $this->getMessagesForApi($agentThread, $agentThreadRun);

        // Handle streaming if enabled
        if ($apiOptions->isStreaming()) {
            $response = $this->executeStreamingResponsesApi($agentThread, $messages, $apiOptions);
        } else {
            // Regular non-streaming Responses API call
            $agent    = $agentThread->agent;
            $api      = $agent->getModelApi();
            $response = $api->responses(
                $agent->model,
                $messages,
                $apiOptions
            );

            // Capture the ApiLog ID from the API instance
            $apiLog = $api->getCurrentApiLog();
            if ($apiLog) {
                $this->currentApiLogId = $apiLog->id;
            }
        }

        return $response;
    }

    /**
     * Execute streaming Responses API call
     */
    protected function executeStreamingResponsesApi(AgentThread $agentThread, array $messages, ResponsesApiOptions $apiOptions): AgentCompletionResponseContract
    {
        // Create a placeholder message for streaming
        $streamMessage = $agentThread->messages()->create([
            'role'    => AgentThreadMessage::ROLE_ASSISTANT,
            'content' => '',
            'data'    => null,
        ]);

        // Execute streaming request using the separate streamResponses method
        $api      = $agentThread->agent->getModelApi();
        $response = $api->streamResponses(
            $agentThread->agent->model,
            $messages,
            $apiOptions,
            $streamMessage
        );

        // Capture the ApiLog ID from the API instance
        $apiLog = $api->getCurrentApiLog();
        if ($apiLog) {
            $this->currentApiLogId = $apiLog->id;
        }

        return $response;
    }

    /**
     * Build system instructions for Responses API
     */
    protected function buildSystemInstructions(AgentThreadRun $agentThreadRun): string
    {
        $instructions = 'The current date and time is ' . now()->toDateTimeString() . "\n\n";

        if ($agentThreadRun->responseSchema) {
            $instructions .= "\nResponse Schema Name: {$agentThreadRun->responseSchema->name}";

            if ($agentThreadRun->responseFragment) {
                $instructions .= "\nResponse Fragment Name: {$agentThreadRun->responseFragment->name}";
            }
        }

        return $instructions;
    }

    /**
     * Get MCP server configuration for the agent thread run
     */
    protected function getMcpServerConfiguration(AgentThreadRun $agentThreadRun): array
    {
        $mcpServer = $agentThreadRun->mcpServer;

        if (!$mcpServer) {
            return [];
        }

        return [
            [
                'type'             => 'mcp',
                'server_url'       => $mcpServer->server_url,
                'server_label'     => $mcpServer->name,
                'allowed_tools'    => $mcpServer->allowed_tools,
                'headers'          => $mcpServer->headers,
                'require_approval' => 'never', // Allow AI to use tools without approval
            ],
        ];
    }
}
