<?php

namespace App\Services\AgentThread;

use App\Api\AgentApiContracts\AgentCompletionResponseContract;
use App\Api\AgentApiContracts\AgentMessageFormatterContract;
use App\Api\OpenAi\Classes\OpenAiToolCaller;
use App\Jobs\ExecuteThreadRunJob;
use App\Models\Agent\Agent;
use App\Models\Agent\Message;
use App\Models\Agent\Thread;
use App\Models\Agent\ThreadRun;
use App\Repositories\AgentRepository;
use App\Repositories\TeamObjectRepository;
use App\Services\JsonSchema\JsonSchemaService;
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
            // If we are currently in a running job, attach the job dispatch ID to the thread run
            if (Job::$runningJob) {
                $threadRun->job_dispatch_id = Job::$runningJob->id;
                $threadRun->save();
            }
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
                    'type' => $threadRun->response_format ?: Agent::RESPONSE_FORMAT_TEXT,
                ],
                'seed'            => (int)$threadRun->seed,
            ];

            if ($threadRun->response_format === Agent::RESPONSE_FORMAT_JSON_SCHEMA) {
                // Configure the JSON schema service to require the name and id fields, and use citations for each property
                $options['response_format'][Agent::RESPONSE_FORMAT_JSON_SCHEMA] = app(JsonSchemaService::class)
                    ->useCitations()
                    ->requireName()
                    ->useId()
                    ->formatAgentResponseSchema($agent);
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
     * Format the messages to be sent to an AI completion API
     */
    public function getMessagesForApi(Thread $thread): array
    {
        $agent        = $thread->agent;
        $apiFormatter = $agent->getModelApi()->formatter();

        $corePrompt = "The current date and time is " . now()->toDateTimeString() . "\n\n";
        $messages[] = $apiFormatter->rawMessage(Message::ROLE_USER, $corePrompt);

        // Top Directives go before thread messages
        foreach($agent->topDirectives()->get() as $topDirective) {
            if ($topDirective->directive->directive_text) {
                $messages[] = $apiFormatter->rawMessage(Message::ROLE_USER, $topDirective->directive->directive_text);
            }
        }

        // Thread messages are inserted between the directives
        foreach($thread->messages()->get() as $message) {
            $formattedMessage = $apiFormatter->message($message);

            // For agents that rely on citing messages as sources, wrap the message in an AgentMessage tag
            if ($agent->enable_message_sources && ($message->isUser() || $message->isTool())) {
                $messages[] = $apiFormatter->wrapMessage("<AgentMessage id='$message->id'>", $formattedMessage, "</AgentMessage>");
            } else {
                $messages[] = $formattedMessage;
            }
        }

        // Bottom Directives go after thread messages
        foreach($agent->bottomDirectives()->get() as $bottomDirective) {
            if ($bottomDirective->directive->directive_text) {
                $messages[] = $apiFormatter->rawMessage(Message::ROLE_USER, $bottomDirective->directive->directive_text);
            }
        }

        $responseMessage = $this->getResponseMessage($agent, $apiFormatter);

        if ($responseMessage) {
            $messages[] = $apiFormatter->rawMessage(Message::ROLE_USER, $responseMessage);
        }

        return $apiFormatter->messageList($messages);
    }

    /**
     * Get the response message for the AI model
     */
    public function getResponseMessage(Agent $agent, AgentMessageFormatterContract $apiFormatter): string
    {
        $responseMessage = '';

        // JSON Object responses provide a schema for the response, but not via the json_schema structured response mechanics by Open AI (possibly others)
        // So this is just a message to the LLM instead of a requirement built in
        if ($agent->response_format === Agent::RESPONSE_FORMAT_JSON_OBJECT && $agent->responseSchema?->schema) {
            $responseMessage .= "\n\nResponse Schema:\n" . json_encode($agent->responseSchema->schema);
        }

        // If the response format is JSON Schema, but the agent does not accept JSON schema, we need to format the response schema for the AI model
        // and provide a message so the agent can see the schema (simulating response schema format)
        // XXX: NOTE this is a hack for Perplexity AI, which does support JSON Schema, but does not seem to respond to it for their online models
        elseif ($agent->response_format === Agent::RESPONSE_FORMAT_JSON_SCHEMA && !$apiFormatter->acceptsJsonSchema()) {
            $responseMessage .= "\n\nResponse Schema:\n" . json_encode(app(JsonSchemaService::class)->formatAgentResponseSchema($agent));
        }

        // Include the Example response if we're in JSON object mode to help the agent understand the correct response format
        if ($agent->response_format === Agent::RESPONSE_FORMAT_JSON_OBJECT && $agent->responseSchema?->response_example) {
            $responseMessage .= "\n\nExample Response:\n" . json_encode($agent->responseSchema->response_example);
        }

        if ($agent->response_format === Agent::RESPONSE_FORMAT_JSON_SCHEMA) {
            $responseMessage .= "\n\nYour response will be saved to the DB. In order to save correctly, the name attribute must be set as the unique identifier for the object type. " .
                "Your goal is to investigate and decide the best value for each attribute of every object in the response schema. " .
                "Set an attribute value to null if it is not given in the source content. NEVER make up values for an attribute. " .
                "If teamObjects is present, it is provided as a source of reference for what is already saved in the DB. " .
                "Similar names like Johnson and Johnson vs Johnson & Johnson should resolve to the same object. Try to avoid creating duplicate records. " .
                "Only update an attribute if you have a new / better value than what is already in teamObjects by ingesting additional content  (ie: images / files, URLs leading to web pages, additionally provided content, etc.), otherwise leave the attribute value null to avoid updating.";
        }

        if ($agent->response_format !== 'text') {
            $responseMessage .= "\n\nOUTPUT IN JSON FORMAT ONLY! NO OTHER TEXT";
        }

        return $responseMessage;
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

            if ($threadRun->thread->agent->save_response_to_db) {
                app(TeamObjectRepository::class)->saveTeamObjectUsingSchema($threadRun->thread->agent->responseSchema->schema, $jsonData, $threadRun);
            }

            // Call the response tools if they are set
            $responseTools = $jsonData['response_tools'] ?? [];
            if ($responseTools) {
                $this->callResponseTools($threadRun, $responseTools);
            }
        }

        Log::debug("Thread response is finished");
    }

    /**
     * Call the response tools for the thread run
     */
    public function callResponseTools(ThreadRun $threadRun, array $responseTools): void
    {
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
