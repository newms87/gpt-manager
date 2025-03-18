<?php

namespace App\Services\AgentThread;

use App\Api\AgentApiContracts\AgentCompletionResponseContract;
use App\Api\OpenAi\Classes\OpenAiToolCaller;
use App\Jobs\ExecuteThreadRunJob;
use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadMessage;
use App\Models\Agent\AgentThreadRun;
use App\Models\Schema\SchemaDefinition;
use App\Models\Schema\SchemaFragment;
use App\Repositories\AgentRepository;
use App\Services\JsonSchema\JsonSchemaService;
use App\Traits\HasDebugLogging;
use Exception;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Exceptions\ApiRequestException;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\LockHelper;
use Newms87\Danx\Helpers\StringHelper;
use Newms87\Danx\Jobs\Job;
use Throwable;

class AgentThreadService
{
    use HasDebugLogging;

    protected ?SchemaDefinition  $responseSchema    = null;
    protected ?SchemaFragment    $responseFragment  = null;
    protected ?JsonSchemaService $jsonSchemaService = null;

    /**
     * Overrides the response format for the thread run.
     * This will replace the Agent's response format with the provided schema and fragment
     */
    public function withResponseFormat(SchemaDefinition $responseSchema = null, SchemaFragment $responseFragment = null, JsonSchemaService $jsonSchemaService = null): static
    {
        $this->responseSchema    = $responseSchema;
        $this->responseFragment  = $responseFragment;
        $this->jsonSchemaService = $jsonSchemaService;

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
                'temperature'          => $agent->temperature,
                'tools'                => $agent->tools,
                'tool_choice'          => 'auto',
                'response_format'      => $this->responseSchema ? 'json_schema' : 'text',
                'response_schema_id'   => $this->responseSchema?->id,
                'response_fragment_id' => $this->responseFragment?->id,
                'json_schema_config'   => $this->jsonSchemaService?->getConfig(),
                'seed'                 => config('ai.seed'),
                'started_at'           => now(),
            ]);

            // Save a snapshot of the resolved JSON Schema to use as the agent's response schema,
            // and so we can clearly see what the schema was at the time of running the request
            if ($this->responseSchema) {
                if (!$this->responseSchema->schema) {
                    throw new ValidationError("Response schema has no schema defined: " . $this->responseSchema);
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

        return $agentThreadRun;
    }

    /**
     * Stop the currently running thread (if it is running)
     */
    public function stop(AgentThread $thread): AgentThreadRun|null
    {
        LockHelper::acquire($thread);
        $threadRun = $thread->currentRun;
        if ($threadRun) {
            $threadRun->status = AgentThreadRun::STATUS_STOPPED;
            $threadRun->save();
        }
        LockHelper::release($thread);

        return $threadRun;
    }

    /**
     * Resume the previously stopped thread (if there was a stopped thread run)
     */
    public function resume(AgentThread $thread): AgentThreadRun|null
    {
        LockHelper::acquire($thread);
        $threadRun = $thread->runs()->where('status', AgentThreadRun::STATUS_STOPPED)->latest()->first();

        if ($threadRun) {
            $threadRun->status          = AgentThreadRun::STATUS_RUNNING;
            $threadRun->job_dispatch_id = (new ExecuteThreadRunJob($threadRun))->dispatch()->getJobDispatch()?->id;
            $threadRun->save();
        }
        LockHelper::release($thread);

        return $threadRun;
    }

    /**
     * Execute the thread run to completion
     */
    public function executeThreadRun(AgentThreadRun $agentThreadRun): void
    {
        try {
            LockHelper::acquire($agentThreadRun->agentThread);
            LockHelper::acquire($agentThreadRun);

            static::log("Executing $agentThreadRun");

            $agentThread = $agentThreadRun->agentThread;
            $agent       = $agentThread->agent;

            $options = [
                'temperature'     => $agentThreadRun->temperature,
                'response_format' => [
                    'type' => $agentThreadRun->response_format ?: AgentThreadRun::RESPONSE_FORMAT_TEXT,
                ],
                'seed'            => (int)$agentThreadRun->seed,
            ];

            if ($agentThreadRun->response_format === AgentThreadRun::RESPONSE_FORMAT_JSON_SCHEMA) {
                $jsonSchema = $agentThreadRun->response_json_schema;

                if (!$jsonSchema) {
                    throw new Exception("JSON Schema response format requires a schema to be set: " . $agentThreadRun);
                }

                $options['response_format'][AgentThreadRun::RESPONSE_FORMAT_JSON_SCHEMA] = $jsonSchema;
            }

            $tools = $agent->formatTools();

            if ($tools) {
                $options['tool_choice'] = $agentThreadRun->tool_choice;
                $options['tools']       = $tools;
            }

            $response         = null;
            $retries          = $agent->retry_count ?: 0;
            $status500retries = 3;

            do {
                $agentThreadRun->refresh();
                if ($agentThreadRun->status !== AgentThreadRun::STATUS_RUNNING) {
                    static::log("$agentThreadRun is no longer running: " . $agentThreadRun->status);
                    break;
                }

                // Get the messages for the next iteration
                $messages     = $this->getMessagesForApi($agentThread, $agentThreadRun);
                $messageCount = count($messages);
                static::log("$agentThread running with $messageCount messages for $agent");

                try {
                    // Filter out excluded options from the agent configuration
                    $excludedOptions = $agent->getModelConfig('excluded_options');
                    if ($excludedOptions) {
                        $options = array_diff_key($options, array_combine($excludedOptions, $excludedOptions));
                    }

                    $response = $agent->getModelApi()->complete(
                        $agent->model,
                        $messages,
                        $options
                    );
                } catch(ApiRequestException $exception) {
                    if ($exception->getStatusCode() >= 500) {
                        if ($status500retries-- > 0) {
                            Log::warning("500 level error from completion API. Retrying in 5 seconds... (retries left: $status500retries)");
                            sleep(5);
                            continue;
                        }
                    }

                    // If the error is not a 500 level error, or we have exhausted retries, throw the exception
                    throw $exception;
                }

                if ($response->isMessageEmpty() && ($retries-- > 0)) {
                    static::log("Empty response from AI model. Retrying... (retries left: $retries)");
                    continue;
                } elseif ($response->isFinished()) {
                    // If we have a non-empty finished response, no need to retry
                    $retries = 0;
                }

                $this->handleResponse($agentThread, $agentThreadRun, $response);
            } while(!$response?->isFinished() || $retries > 0);
        } catch(Throwable $throwable) {
            $agentThreadRun->status    = AgentThreadRun::STATUS_FAILED;
            $agentThreadRun->failed_at = now();
            $agentThreadRun->save();
            throw $throwable;
        } finally {
            LockHelper::release($agentThreadRun);
            LockHelper::release($agentThreadRun->agentThread);
        }
    }

    /**
     * Format the messages to be sent to an AI completion API
     */
    public function getMessagesForApi(AgentThread $thread, AgentThreadRun $agentThreadRun): array
    {
        $agent        = $thread->agent;
        $apiFormatter = $agent->getModelApi()->formatter();

        $corePrompt = "The current date and time is " . now()->toDateTimeString() . "\n\n";
        $corePrompt .= "You're an agent created by a user to perform a task.\nYour Name: {$thread->agent->name}";

        if ($thread->agent->description) {
            $corePrompt .= "\nDescription: {$thread->agent->description}";
        }

        if ($agentThreadRun->responseSchema) {
            $corePrompt .= "\nResponse Schema Name: {$agentThreadRun->responseSchema->name}";

            if ($agentThreadRun->responseFragment) {
                $corePrompt .= "\nResponse Fragment Name: {$agentThreadRun->responseFragment->name}";
            }
        }

        $messages[] = $apiFormatter->rawMessage(AgentThreadMessage::ROLE_USER, $corePrompt);

        // Top Directives go before thread messages
        foreach($agent->topDirectives()->get() as $topDirective) {
            if ($topDirective->directive->directive_text) {
                $messages[] = $apiFormatter->rawMessage(AgentThreadMessage::ROLE_USER, $topDirective->directive->directive_text);
            }
        }

        // AgentThread messages are inserted between the directives
        foreach($thread->messages()->get() as $message) {
            $formattedMessage = $apiFormatter->message($message);

            // For agents that rely on citing messages as sources, wrap the message in an AgentMessage tag
            if ($agentThreadRun->getJsonSchemaService()->isUsingCitations() && ($message->isUser() || $message->isTool())) {
                $messages[] = $apiFormatter->wrapMessage("<AgentMessage id='$message->id'>", $formattedMessage, "</AgentMessage>");
            } else {
                $messages[] = $formattedMessage;
            }
        }

        // Bottom Directives go after thread messages
        foreach($agent->bottomDirectives()->get() as $bottomDirective) {
            if ($bottomDirective->directive->directive_text) {
                $messages[] = $apiFormatter->rawMessage(AgentThreadMessage::ROLE_USER, $bottomDirective->directive->directive_text);
            }
        }

        $responseMessage = $this->getResponseMessage($agentThreadRun);

        if ($responseMessage) {
            $messages[] = $apiFormatter->rawMessage(AgentThreadMessage::ROLE_USER, $responseMessage);
        }

        return $apiFormatter->messageList($messages);
    }

    /**
     * Get the response message for the AI model
     */
    public function getResponseMessage(AgentThreadRun $agentThreadRun): string
    {
        $responseMessage = '';

        if ($agentThreadRun->response_format === AgentThreadRun::RESPONSE_FORMAT_JSON_SCHEMA && $agentThreadRun->getJsonSchemaService()->isUsingDbFields()) {
            $responseMessage .= <<<STR
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
        $inputTokens  = $threadRun->input_tokens + $response->inputTokens();
        $outputTokens = $threadRun->output_tokens + $response->outputTokens();

        static::log("Handling response from AI model. input: " . $inputTokens . ", output: " . $outputTokens);

        $threadRun->update([
            'agent_model'   => $thread->agent->model,
            'refreshed_at'  => now(),
            'total_cost'    => app(AgentRepository::class)->calcTotalCost($thread->agent, $inputTokens, $outputTokens),
            'input_tokens'  => $inputTokens,
            'output_tokens' => $outputTokens,
        ]);

        $lastMessage = $thread->messages()->create([
            'role'    => AgentThreadMessage::ROLE_ASSISTANT,
            'content' => $response->getContent(),
            'data'    => $response->getDataFields() ?: null,
        ]);

        if (!$response->isToolCall() && !$response->isFinished()) {
            throw new Exception('Unexpected response from AI model');
        }

        if ($response->isToolCall()) {
            // Check for duplicated tool calls and immediately stop thread so we don't waste resources
            if ($this->hasDuplicatedToolCall($threadRun, $response)) {
                Log::error("Duplicated tool call detected, stopping thread");
                $lastMessage = $thread->messages()->create([
                    'role'    => AgentThreadMessage::ROLE_USER,
                    'content' => "Duplicated tool call detected, stopping thread",
                ]);
                $this->finishThreadResponse($threadRun, $lastMessage);
            } else {
                $this->callToolsWithToolResponse($thread, $threadRun, $response);
                static::log("Tool call response completed.");
            }
        }

        if ($response->isFinished()) {
            $this->finishThreadResponse($threadRun, $lastMessage);
        }
    }

    /**
     * Finish the thread response by updating the thread run and calling the response tools (if any)
     */
    public function finishThreadResponse(AgentThreadRun $threadRun, AgentThreadMessage $lastMessage): void
    {
        static::log("Finishing thread response...");

        $threadRun->update([
            'status'          => AgentThreadRun::STATUS_COMPLETED,
            'completed_at'    => now(),
            'last_message_id' => $lastMessage->id,
        ]);

        if ($lastMessage->content) {
            $jsonData = $lastMessage->getJsonContent();

            // Call the response tools if they are set
            $responseTools = $jsonData['response_tools'] ?? [];
            if ($responseTools) {
                $this->callResponseTools($threadRun, $responseTools);
            }
        }

        static::log("AgentThread response is finished");
    }

    /**
     * Call the response tools for the thread run
     */
    public function callResponseTools(AgentThreadRun $threadRun, array $responseTools): void
    {
        static::log("Finishing thread response with " . count($responseTools) . " response tools");

        foreach($responseTools as $tool) {
            $toolName = $tool['name'] ?? null;

            if (!$toolName) {
                throw new Exception("Response tool name is required: \n" . json_encode($tool));
            }

            static::log("Handling tool call: " . $toolName);

            $toolCaller = new OpenAiToolCaller(
                '',
                $tool['name'],
                json_decode($tool['arguments'], true)
            );

            $toolCaller->call($threadRun);
        }
    }

    /**
     * Call the AI Tools and attach the response from the tools to the thread for further processing by the AI Agent
     */
    public function callToolsWithToolResponse(AgentThread $thread, AgentThreadRun $threadRun, AgentCompletionResponseContract $response): void
    {
        static::log("Completion Response: Handling " . count($response->getToolCallerFunctions()) . " tool calls");

        // Additional messages that support the tool response, such as images
        // These messages must appear after all tool responses,
        // otherwise ChatGPT will throw an error thinking it is missing tool response messages
        $additionalMessages = [];

        // Call the tool functions
        foreach($response->getToolCallerFunctions() as $toolCallerFunction) {
            static::log("Handling tool call: " . $toolCallerFunction->getName());

            $messages = $toolCallerFunction->call($threadRun);

            if ($messages) {
                // Add the tool message
                $toolMessage = array_shift($messages);
                $thread->messages()->create($toolMessage);

                // Append the additional messages to the list to appear after all tool responses
                $additionalMessages = array_merge($additionalMessages, $messages);
            }
        }

        // Save all the tool response messages
        foreach($additionalMessages as $message) {
            if (isset($message['content']) && is_array($message['content'])) {
                $message['content'] = StringHelper::safeJsonEncode($message['content']);
            }
            $thread->messages()->create($message);
        }
    }

    /**
     * Check if the thread run has already made the exact same tool call in the current thread.
     * This will avoid any looping by the agent
     */
    public function hasDuplicatedToolCall(AgentThreadRun $threadRun, AgentCompletionResponseContract $response): bool
    {
        $assistantMessages = $threadRun->agentThread->messages()->where('role', AgentThreadMessage::ROLE_ASSISTANT)->get();

        $toolCallHashes = [];

        foreach($assistantMessages as $message) {
            if (!$message->data || empty($message->data['tool_calls'])) {
                continue;
            }

            $toolCalls = $message->data['tool_calls'];

            foreach($toolCalls as $toolCall) {
                if (empty($toolCall['function'])) {
                    continue;
                }
                $hash = md5(json_encode($toolCall['function']));

                if (!empty($toolCallHashes[$hash])) {
                    return true;
                }

                $toolCallHashes[$hash] = true;
            }
        }

        return false;
    }
}
