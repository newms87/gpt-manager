<?php

namespace App\Services\AgentThread;

use App\Api\AgentApiContracts\AgentCompletionResponseContract;
use App\Api\OpenAi\Classes\OpenAiToolCaller;
use App\Jobs\ExecuteThreadRunJob;
use App\Models\Agent\Message;
use App\Models\Agent\Thread;
use App\Models\Agent\ThreadRun;
use App\Repositories\AgentRepository;
use Exception;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\LockHelper;
use Newms87\Danx\Helpers\StringHelper;
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
            'response_format' => $agent->response_format === 'text' ? 'text' : 'json_object',
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

            $tools = $agent->formatTools();

            if ($tools) {
                $options['tool_choice'] = $threadRun->tool_choice;
                $options['tools']       = $tools;
            }

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

                $response = $agent->getModelApi()->complete(
                    $agent->model,
                    $messages,
                    $options
                );

                $this->handleResponse($thread, $threadRun, $response);
            } while(!$response->isFinished());
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
        $messages[] = $apiFormatter->rawMessage(Message::ROLE_USER, $corePrompt . $agent->prompt);

        foreach($thread->messages()->get() as $message) {
            $messages[] = $apiFormatter->message($message);
        }

        if ($agent->response_notes || $agent->response_schema) {
            if ($agent->response_format === 'text') {
                $jsonRequirement = '';
                $schema          = '';
            } else {
                $schema          = json_encode($agent->response_schema);
                $jsonRequirement = "\nOUTPUT IN JSON FORMAT ONLY! NO OTHER TEXT\n";
            }
            $messages[] = $apiFormatter->rawMessage(Message::ROLE_USER, "RESPONSE FORMAT:\n$agent->response_notes\n$jsonRequirement\n$schema");
        }

        return $messages;
    }


    /**
     * Handle the response from the AI model
     */
    public function handleResponse(Thread $thread, ThreadRun $threadRun, AgentCompletionResponseContract $response): void
    {
        $inputTokens  = $threadRun->input_tokens + $response->inputTokens();
        $outputTokens = $threadRun->output_tokens + $response->outputTokens();

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
                $this->callToolsWithToolResponse($thread, $response);
            }
        } elseif ($response->isFinished()) {
            $this->finishThreadResponse($threadRun, $lastMessage);
        } else {
            throw new Exception('Unexpected response from AI model');
        }
    }

    /**
     * Finish the thread response by updating the thread run and calling the response tools (if any)
     */
    public function finishThreadResponse(ThreadRun $threadRun, Message $lastMessage): void
    {
        $threadRun->update([
            'status'          => ThreadRun::STATUS_COMPLETED,
            'completed_at'    => now(),
            'last_message_id' => $lastMessage->id,
        ]);

        if ($lastMessage->content) {
            $jsonData = json_decode(AgentThreadService::cleanContent($lastMessage->content), true);

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

                    $toolCaller->call();
                }
            }
        }

        Log::debug("Thread response is finished");
    }

    /**
     * Call the AI Tools and attach the response from the tools to the thread for further processing by the AI Agent
     */
    public function callToolsWithToolResponse(Thread $thread, AgentCompletionResponseContract $response): void
    {
        Log::debug("Completion Response: Handling " . count($response->getToolCallerFunctions()) . " tool calls");

        // Additional messages that support the tool response, such as images
        // These messages must appear after all tool responses,
        // otherwise ChatGPT will throw an error thinking it is missing tool response messages
        $additionalMessages = [];

        // Call the tool functions
        foreach($response->getToolCallerFunctions() as $toolCallerFunction) {
            Log::debug("Handling tool call: " . $toolCallerFunction->getName());

            $messages = $toolCallerFunction->call();

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

    /**
     * Cleans the AI Model responses to make sure we have valid JSON, if the response is JSON
     */
    public static function cleanContent($content): string
    {
        // Remove any ```json and trailing ``` from content if they are present
        return preg_replace('/^```json\n(.*)\n```$/s', '$1', trim($content));
    }
}
