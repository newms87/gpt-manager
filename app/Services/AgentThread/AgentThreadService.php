<?php

namespace App\Services\AgentThread;

use App\Api\AgentApiContracts\AgentCompletionResponseContract;
use App\Api\Options\ResponsesApiOptions;
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
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Exceptions\ApiRequestException;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\LockHelper;
use Newms87\Danx\Jobs\Job;
use Throwable;

class AgentThreadService
{
    use HasDebugLogging;

    protected ?SchemaDefinition    $responseSchema      = null;
    protected ?SchemaFragment      $responseFragment    = null;
    protected ?JsonSchemaService   $jsonSchemaService   = null;
    protected ?ResponsesApiOptions $responsesApiOptions = null;

    protected array $retryProfile = [
        'invalid_image_url' => [
            'retries' => 3,
            'delay'   => 5,
        ],
    ];

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
                'api_options'          => $agent->api_options,
                'response_format'      => $this->responseSchema ? 'json_schema' : 'text',
                'response_schema_id'   => $this->responseSchema?->id,
                'response_fragment_id' => $this->responseFragment?->id,
                'json_schema_config'   => $this->jsonSchemaService?->getConfig(),
                'seed'                 => config('ai.seed'),
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
            $threadRun->stopped_at = now();
            $threadRun->save();
        }
        LockHelper::release($thread);

        return $threadRun;
    }

    /**
     * Resume the previously stopped thread (if there was a stopped thread run)
     */
    public function resume(AgentThread $agentThread): AgentThreadRun|null
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
            LockHelper::acquire($agentThreadRun->agentThread);
            LockHelper::acquire($agentThreadRun);

            $agentThreadRun->started_at = now();
            $agentThreadRun->save();

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

            $response         = null;
            $retries          = $agent->retry_count ?: 0;
            $status500retries = 3;

            do {
                $agentThreadRun->refresh();
                if (!$agentThreadRun->isStatusRunning()) {
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

                    // Always use Responses API (completions API removed)
                    $response = $this->executeResponsesApi($agent, $agentThread, $agentThreadRun, $messages, $options);
                } catch(ConnectException $exception) {
                    // Handle connection errors
                    if (str_contains($exception->getMessage(), 'timed out') && ($retries-- > 0)) {
                        // Apply a random exponential backoff strategy
                        $exponentialTimeout = random_int(5, 10) * max(10, pow(3, $agent->retry_count - $retries - 1));
                        Log::warning("Connection timed out from Responses API. Retrying in $exponentialTimeout seconds...");
                        sleep($exponentialTimeout);
                        continue;
                    }

                    // If the error is not a connection error, throw the exception
                    throw $exception;
                } catch(ApiRequestException $exception) {
                    if ($exception->getStatusCode() >= 500) {
                        if ($status500retries-- > 0) {
                            Log::warning("500 level error from completion API. Retrying in 5 seconds... (retries left: $status500retries)");
                            sleep(5);
                            continue;
                        }
                    } elseif ($exception->getStatusCode() === 400) {
                        if ($this->shouldRetry($exception->getJson())) {
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
            $agentThreadRun->failed_at = now();
            $agentThreadRun->save();
            throw $throwable;
        } finally {
            LockHelper::release($agentThreadRun);
            LockHelper::release($agentThreadRun->agentThread);
        }
    }

    public function shouldRetry(array $responseJson): bool
    {
        $errorCode = $responseJson['error']['code'] ?? null;

        Log::error("Agent returned error code: $errorCode");

        if (!$errorCode) {
            return false;
        }

        $profile = $this->retryProfile[$errorCode] ?? null;

        if ($profile) {
            $retries = $profile['retries'] ?? 0;
            $delay   = $profile['delay'] ?? 0;

            if ($retries > 0) {
                Log::warning("Error code $errorCode: Retrying in $delay seconds... (retries left: $retries)");
                $this->retryProfile[$errorCode]['retries'] = $retries - 1;
                sleep($delay);

                return true;
            }
        }

        return true;
    }

    /**
     * Format the messages to be sent to an AI completion API
     */
    public function getMessagesForApi(AgentThread $thread, AgentThreadRun $agentThreadRun): array
    {
        $agent        = $thread->agent;
        $apiFormatter = $agent->getModelApi()->formatter();

        // For Responses API, we'll handle the instructions separately in the executeResponsesApi method
        $messages = [];

        // AgentThread messages are inserted between the directives
        foreach($thread->messages()->get() as $message) {
            $formattedMessage = $apiFormatter->message($message);

            // For agents that rely on citing messages as sources, wrap the message in an AgentMessage tag
            if ($agentThreadRun->getJsonSchemaService()->isUsingCitations() && $message->isUser()) {
                $messages[] = $apiFormatter->wrapMessage("<AgentMessage id='$message->id'>", $formattedMessage, "</AgentMessage>");
            } else {
                $messages[] = $formattedMessage;
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
        static::log("Finishing thread response...");

        $threadRun->update([
            'status'          => AgentThreadRun::STATUS_COMPLETED,
            'completed_at'    => now(),
            'last_message_id' => $lastMessage->id,
        ]);


        static::log("AgentThread response is finished");
    }

    /**
     * Execute the thread run using the Responses API with response ID optimization
     */
    protected function executeResponsesApi($agent, AgentThread $thread, AgentThreadRun $threadRun, array $messages, array $baseOptions): AgentCompletionResponseContract
    {
        // Get API options from the thread run (source of truth) or use defaults
        $apiOptions = $threadRun->api_options ? ResponsesApiOptions::fromArray($threadRun->api_options) : new ResponsesApiOptions();

        // Build system instructions and always prepend them
        $systemInstructions   = $this->buildSystemInstructions($thread, $threadRun);
        $existingInstructions = $apiOptions->getInstructions();

        if ($existingInstructions) {
            // Prepend system instructions to existing instructions
            $apiOptions->setInstructions($systemInstructions . "\n\n" . $existingInstructions);
        } else {
            // Set system instructions as the main instructions
            $apiOptions->setInstructions($systemInstructions);
        }

        // Optimize with previous response ID - only send new messages since last tracked response
        $lastTrackedMessage = AgentThreadMessage::getLastTrackedMessageInThread($thread);
        if ($lastTrackedMessage) {
            // Use previous response ID for optimization
            $apiOptions->setPreviousResponseId($lastTrackedMessage->api_response_id);

            // Only get unsent messages (messages created after the last tracked response)
            $messages = AgentThreadMessage::getUnsentMessagesInThread($thread);

            static::log("Using previous response ID optimization: {$lastTrackedMessage->api_response_id}. Sending " . count($messages) . " new messages.");
        } else {
            static::log("No previous response ID found. Sending all " . count($messages) . " messages.");
        }

        // Handle streaming if enabled
        if ($apiOptions->isStreaming()) {
            return $this->executeStreamingResponsesApi($agent, $thread, $threadRun, $messages, $apiOptions);
        }

        // Regular non-streaming Responses API call
        $response = $agent->getModelApi()->responses(
            $agent->model,
            $messages,
            $apiOptions
        );

        // Track the response ID for future optimization
        if (method_exists($response, 'getResponseId')) {
            // Find the assistant message created for this response and track it
            $assistantMessage = $thread->messages()
                ->where('role', AgentThreadMessage::ROLE_ASSISTANT)
                ->whereNull('api_response_id')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($assistantMessage) {
                $assistantMessage->setApiResponseId($response->getResponseId());
                static::log("Tracked response ID: {$response->getResponseId()} for message {$assistantMessage->id}");
            }
        }

        return $response;
    }

    /**
     * Execute streaming Responses API call
     */
    protected function executeStreamingResponsesApi($agent, AgentThread $thread, AgentThreadRun $threadRun, array $messages, ResponsesApiOptions $apiOptions): AgentCompletionResponseContract
    {
        // Create a placeholder message for streaming
        $streamMessage = $thread->messages()->create([
            'role'    => AgentThreadMessage::ROLE_ASSISTANT,
            'content' => '',
            'data'    => null,
        ]);

        // Execute streaming request using the separate streamResponses method
        $response = $agent->getModelApi()->streamResponses(
            $agent->model,
            $messages,
            $apiOptions,
            $streamMessage
        );

        // Track the response ID for future optimization
        if (method_exists($response, 'getResponseId')) {
            $streamMessage->setApiResponseId($response->getResponseId());
            static::log("Tracked streaming response ID: {$response->getResponseId()} for message {$streamMessage->id}");
        }

        return $response;
    }

    /**
     * Build system instructions for Responses API
     */
    protected function buildSystemInstructions(AgentThread $thread, AgentThreadRun $threadRun): string
    {
        $instructions = "The current date and time is " . now()->toDateTimeString() . "\n\n";
        $instructions .= "You're an agent created by a user to perform a task.\nYour Name: {$thread->agent->name}";

        if ($thread->agent->description) {
            $instructions .= "\nDescription: {$thread->agent->description}";
        }

        if ($threadRun->responseSchema) {
            $instructions .= "\nResponse Schema Name: {$threadRun->responseSchema->name}";

            if ($threadRun->responseFragment) {
                $instructions .= "\nResponse Fragment Name: {$threadRun->responseFragment->name}";
            }
        }

        return $instructions;
    }
}
