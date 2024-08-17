<?php

namespace App\Services\AgentThread;

use App\Api\AgentApiContracts\AgentCompletionResponseContract;
use App\Api\OpenAi\Classes\OpenAiToolCaller;
use App\Jobs\ExecuteThreadRunJob;
use App\Models\Agent\Agent;
use App\Models\Agent\Message;
use App\Models\Agent\Thread;
use App\Models\Agent\ThreadRun;
use App\Repositories\AgentRepository;
use Exception;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Exceptions\ApiRequestException;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\LockHelper;
use Newms87\Danx\Helpers\StringHelper;
use Str;
use Throwable;

class AgentThreadService
{
    /**
     * Run the thread with the agent by calling the AI model API
     */
    public function run(Thread $thread, $dispatch = true): ThreadRun
    {
        LockHelper::acquire($thread);

        if ($thread->isRunning()) {
            throw new ValidationError('The thread is already running.');
        }

        $agent = $thread->agent;

        if ($thread->messages()->doesntExist()) {
            throw new ValidationError('You must add messages to the thread before running it.');
        }

        $threadRun = $thread->runs()->create([
            'agent_model'     => $agent->model,
            'status'          => ThreadRun::STATUS_RUNNING,
            'temperature'     => $agent->temperature,
            'tools'           => $agent->tools,
            'tool_choice'     => 'auto',
            'response_format' => $agent->response_format,
            'seed'            => config('ai.seed'),
            'started_at'      => now(),
        ]);

        // Execute the thread run in a job
        if ($dispatch) {
            $job                        = (new ExecuteThreadRunJob($threadRun))->dispatch();
            $threadRun->job_dispatch_id = $job->getJobDispatch()?->id;
            $threadRun->save();
        } else {
            $this->executeThreadRun($threadRun);
        }

        LockHelper::release($thread);

        return $threadRun;
    }

    /**
     * Stop the currently running thread (if it is running)
     */
    public function stop(Thread $thread): ThreadRun|null
    {
        LockHelper::acquire($thread);
        $threadRun = $thread->currentRun;
        if ($threadRun) {
            $threadRun->status = ThreadRun::STATUS_STOPPED;
            $threadRun->save();
        }
        LockHelper::release($thread);

        return $threadRun;
    }

    /**
     * Resume the previously stopped thread (if there was a stopped thread run)
     */
    public function resume(Thread $thread): ThreadRun|null
    {
        LockHelper::acquire($thread);
        $threadRun = $thread->runs()->where('status', ThreadRun::STATUS_STOPPED)->latest()->first();

        if ($threadRun) {
            $threadRun->status          = ThreadRun::STATUS_RUNNING;
            $threadRun->job_dispatch_id = (new ExecuteThreadRunJob($threadRun))->dispatch()->getJobDispatch()?->id;
            $threadRun->save();
        }
        LockHelper::release($thread);

        return $threadRun;
    }

    /**
     * Execute the thread run to completion
     */
    public function executeThreadRun(ThreadRun $threadRun): void
    {
        try {
            Log::debug("Executing $threadRun");

            $thread = $threadRun->thread;
            $agent  = $thread->agent;

            $options = [
                'temperature'     => $threadRun->temperature,
                'response_format' => [
                    'type' => $threadRun->response_format ?: 'text',
                ],
                'seed'            => (int)$threadRun->seed,
            ];

            if ($threadRun->response_format === 'json_schema') {
                $options['response_format']['json_schema'] = $this->formatResponseSchemaForAgent($agent);
            }

            $tools = $agent->formatTools();

            if ($tools) {
                $options['tool_choice'] = $threadRun->tool_choice;
                $options['tools']       = $tools;
            }

            $response         = null;
            $retries          = $agent->retry_count ?: 0;
            $status500retries = 3;

            do {
                $threadRun->refresh();
                if ($threadRun->status !== ThreadRun::STATUS_RUNNING) {
                    Log::debug("$threadRun is no longer running: " . $threadRun->status);
                    break;
                }

                // Get the messages for the next iteration
                $messages     = $this->getMessagesForApi($thread);
                $messageCount = count($messages);
                Log::debug("$thread running with $messageCount messages for $agent");

                try {
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
                    Log::debug("Empty response from AI model. Retrying... (retries left: $retries)");
                    continue;
                } elseif ($response->isFinished()) {
                    // If we have a non-empty finished response, no need to retry
                    $retries = 0;
                }

                $this->handleResponse($thread, $threadRun, $response);
            } while(!$response?->isFinished() || $retries > 0);
        } catch(Throwable $throwable) {
            $threadRun->status    = ThreadRun::STATUS_FAILED;
            $threadRun->failed_at = now();
            $threadRun->save();
            throw $throwable;
        }
    }

    /**
     * Format the response schema for the AI model based on the agent's name and response_schema
     */
    public function formatResponseSchemaForAgent(Agent $agent): array|string
    {
        if (is_array($agent->response_schema)) {
            $name = $agent->name . ':' . substr(md5(json_encode($agent->response_schema)), 0, 7);

            return $this->formatResponseSchema(Str::slug($name), $agent->response_schema);
        }

        return $agent->response_schema;
    }

    /**
     * Format the response schema for the AI model
     * @param string $name   The name for this version of the schema
     * @param array  $schema The schema to format. The schema should be properly formatted JSON schema (starting with
     *                       properties of an object as they will be nested inside the main schema)
     * @param int    $depth  The depth of the schema (used for recursion)
     * @return array
     * @throws Exception
     */
    public function formatResponseSchema(string $name, array $schema, int $depth = 0): array
    {
        if (!$schema) {
            throw new Exception("$name in schema is empty");
        }

        $formattedSchema = [];

        // Ensures all properties (and sub properties) are both required and have no additional properties. It does this recursively.
        foreach($schema as $key => $value) {
            $childName             = $name . '.' . $key;
            $formattedSchema[$key] = $this->formatResponseSchemaItem($childName, $value, $depth);
        }

        if ($depth > 0) {
            return $formattedSchema;
        }

        return [
            'name'   => $name,
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'additionalProperties' => false,
                'required'             => array_keys($schema),
                'properties'           => $formattedSchema,
            ],
        ];
    }

    /**
     * Format the response schema to match the requirements of JSON schema for completions API
     */
    public function formatResponseSchemaItem($name, $value, $depth = 0): array
    {
        $type        = $value['type'] ?? null;
        $description = $value['description'] ?? null;
        $enum        = $value['enum'] ?? null;
        $properties  = $value['properties'] ?? [];
        $items       = $value['items'] ?? [];

        $resolvedType = is_array($type) ? $type[0] : $type;

        $item = match ($resolvedType) {
            'object' => [
                'type'                 => $type,
                'properties'           => $this->formatResponseSchema("$name.properties", $properties, $depth + 1),
                'required'             => array_keys($properties),
                'additionalProperties' => false,
            ],
            'array' => [
                'type'  => $type,
                'items' => $this->formatResponseSchemaItem("$name.items", $items, $depth + 1),
            ],
            'string', 'number', 'integer', 'boolean', 'null' => ['type' => $type],
            default => throw new Exception("Unknown type at path $name: " . $type),
        };

        if ($description) {
            $item['description'] = $description;
        }

        if ($enum) {
            $item['enum'] = $enum;
        }

        return $item;
    }

    /**
     * Format the messages to be sent to an AI completion API
     */
    public function getMessagesForApi(Thread $thread): array
    {
        $agent        = $thread->agent;
        $apiFormatter = $agent->getModelApi()->formatter();

        $corePrompt = "The current date and time is " . now()->toDateTimeString() . "\n\n";
        $messages[] = $apiFormatter->rawMessage(Message::ROLE_USER, $corePrompt . $agent->prompt);

        foreach($thread->messages()->get() as $message) {
            $formattedMessage = $apiFormatter->message($message);

            // For agents that rely on citing messages as sources, wrap the message in an AgentMessage tag
            if ($agent->enable_message_sources && ($message->isUser() || $message->isTool())) {
                $messages[] = $apiFormatter->wrapMessage("<AgentMessage id='$message->id'>", $formattedMessage, "</AgentMessage>");
            } else {
                $messages[] = $formattedMessage;
            }
        }

        $responseMessage = '';
        $responseSchema  = null;

        if ($agent->response_notes) {
            $responseMessage = "RESPONSE NOTES:\n$agent->response_notes";
        }

        // JSON Object responses provide a schema for the response, but not via the json_schema structured response mechanics by Open AI (possibly others)
        // So this is just a message to the LLM instead of a requirement built in
        if ($agent->response_format === 'json_object' && $agent->response_schema) {
            $responseSchema = $agent->response_schema;
        }

        // If the response format is JSON Schema, but the agent does not accept JSON schema, we need to format the response schema for the AI model
        // and provide a message so the agent can see the schema (simulating response schema format)
        // XXX: NOTE this is a hack for Perplexity AI, which does support JSON Schema, but does not seem to respond to it for their online models
        if ($agent->response_format === 'json_schema' && !$apiFormatter->acceptsJsonSchema()) {
            $responseSchema = $this->formatResponseSchemaForAgent($agent);
        }

        if ($responseSchema) {
            $responseMessage .= json_encode($responseSchema);
            $responseMessage .= "\nOUTPUT IN JSON FORMAT ONLY! NO OTHER TEXT\n";
        }

        if ($responseMessage) {
            $messages[] = $apiFormatter->rawMessage(Message::ROLE_USER, $responseMessage);
        }

        return $apiFormatter->messageList($messages);
    }

    /**
     * Handle the response from the AI model
     */
    public function handleResponse(Thread $thread, ThreadRun $threadRun, AgentCompletionResponseContract $response): void
    {
        $inputTokens  = $threadRun->input_tokens + $response->inputTokens();
        $outputTokens = $threadRun->output_tokens + $response->outputTokens();

        Log::debug("Handling response from AI model. input: " . $inputTokens . ", output: " . $outputTokens);

        $threadRun->update([
            'agent_model'   => $thread->agent->model,
            'refreshed_at'  => now(),
            'total_cost'    => app(AgentRepository::class)->calcTotalCost($thread->agent, $inputTokens, $outputTokens),
            'input_tokens'  => $inputTokens,
            'output_tokens' => $outputTokens,
        ]);

        $lastMessage = $thread->messages()->create([
            'role'    => Message::ROLE_ASSISTANT,
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
                    'role'    => Message::ROLE_USER,
                    'content' => "Duplicated tool call detected, stopping thread",
                ]);
                $this->finishThreadResponse($threadRun, $lastMessage);
            } else {
                $this->callToolsWithToolResponse($thread, $threadRun, $response);
                Log::debug("Tool call response completed.");
            }
        }

        if ($response->isFinished()) {
            $this->finishThreadResponse($threadRun, $lastMessage);
        }
    }

    /**
     * Finish the thread response by updating the thread run and calling the response tools (if any)
     */
    public function finishThreadResponse(ThreadRun $threadRun, Message $lastMessage): void
    {
        Log::debug("Finishing thread response...");

        $threadRun->update([
            'status'          => ThreadRun::STATUS_COMPLETED,
            'completed_at'    => now(),
            'last_message_id' => $lastMessage->id,
        ]);

        if ($lastMessage->content) {
            $jsonData = $lastMessage->getJsonContent();

            $responseTools = $jsonData['response_tools'] ?? [];

            if ($responseTools) {
                Log::debug("Finishing thread response with " . count($responseTools) . " response tools");

                foreach($responseTools as $tool) {
                    $toolName = $tool['name'] ?? null;

                    if (!$toolName) {
                        throw new Exception("Response tool name is required: \n" . json_encode($tool));
                    }

                    Log::debug("Handling tool call: " . $toolName);

                    $toolCaller = new OpenAiToolCaller(
                        '',
                        $tool['name'],
                        json_decode($tool['arguments'], true)
                    );

                    $toolCaller->call($threadRun);
                }
            }
        }

        Log::debug("Thread response is finished");
    }

    /**
     * Call the AI Tools and attach the response from the tools to the thread for further processing by the AI Agent
     */
    public function callToolsWithToolResponse(Thread $thread, ThreadRun $threadRun, AgentCompletionResponseContract $response): void
    {
        Log::debug("Completion Response: Handling " . count($response->getToolCallerFunctions()) . " tool calls");

        // Additional messages that support the tool response, such as images
        // These messages must appear after all tool responses,
        // otherwise ChatGPT will throw an error thinking it is missing tool response messages
        $additionalMessages = [];

        // Call the tool functions
        foreach($response->getToolCallerFunctions() as $toolCallerFunction) {
            Log::debug("Handling tool call: " . $toolCallerFunction->getName());

            $messages = $toolCallerFunction->call($threadRun);

            // Add the tool message
            $toolMessage = array_shift($messages);
            $thread->messages()->create($toolMessage);

            // Append the additional messages to the list to appear after all tool responses
            $additionalMessages = array_merge($additionalMessages, $messages);
        }

        // Save all the tool response messages
        foreach($additionalMessages as $message) {
            if (isset($message['content']) && is_array($message['content'])) {
                $message['content'] = StringHelper::safeJsonEncode($message['content']);
            }
            $thread->messages()->create($message);
        }
    }

    public function hasDuplicatedToolCall(ThreadRun $threadRun, AgentCompletionResponseContract $response): bool
    {
        $assistantMessages = $threadRun->thread->messages()->where('role', Message::ROLE_ASSISTANT)->get();

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
