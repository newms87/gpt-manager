<?php

namespace App\Repositories;

use App\Api\AgentApiContracts\AgentCompletionResponseContract;
use App\Models\Agent\Agent;
use App\Models\Agent\Message;
use App\Models\Agent\Thread;
use App\Models\Agent\ThreadRun;
use Exception;
use Flytedan\DanxLaravel\Exceptions\ValidationError;
use Flytedan\DanxLaravel\Helpers\DateHelper;
use Flytedan\DanxLaravel\Repositories\ActionRepository;

class ThreadRepository extends ActionRepository
{
    public static string $model = Thread::class;

    public function create(Agent $agent, $name = ''): Thread
    {
        if (!$name) {
            $name = $agent->name . " " . DateHelper::formatDateTime(now());
        }

        $thread = Thread::make()->forceFill([
            'team_id'  => user()->team_id,
            'user_id'  => user()->id,
            'name'     => $name,
            'agent_id' => $agent->id,
        ]);
        $thread->save();

        return $thread;
    }

    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
            'create-message' => app(MessageRepository::class)->create($model, $data['role'] ?? Message::ROLE_USER),
            'run' => $this->run($model),
            default => parent::applyAction($action, $model, $data)
        };
    }

    public function run(Thread $thread)
    {
        $agent    = $thread->agent;
        $messages = $thread->getMessagesForApi();

        if (!$messages) {
            throw new ValidationError('You must add messages to the thread before running it.');
        }

        $threadRun = $thread->runs()->create([
            'status'          => ThreadRun::STATUS_RUNNING,
            'temperature'     => $agent->temperature,
            'tools'           => $agent->tools,
            'tool_choice'     => 'auto',
            'response_format' => 'text',
            'seed'            => config('ai.seed'),
            'started_at'      => now(),
        ]);

        $options = [
            'temperature'     => $threadRun->temperature,
            'tool_choice'     => $threadRun->tool_choice,
            'response_format' => $threadRun->response_format,
            'seed'            => $threadRun->seed,
        ];

        $tools = $agent->formatTools();

        if ($tools) {
            $options['tools'] = $tools;
        }

        do {
            $response = $agent->getModelApi()->complete(
                $agent->model,
                $messages,
                $options
            );

            $this->handleResponse($thread, $threadRun, $response);

            // Get the messages for the next iteration
            $messages = $thread->getMessagesForApi();
        } while(!$response->isFinished());

        return $threadRun;
    }

    public function handleResponse(Thread $thread, ThreadRun $threadRun, AgentCompletionResponseContract $response): void
    {
        if ($response->isToolCall()) {
            $thread->messages()->create([
                'role'    => Message::ROLE_ASSISTANT,
                'content' => $response->getContent(),
                'data'    => $response->getDataFields(),
            ]);

            foreach($response->getToolCallerFunctions() as $toolCallerFunction) {
                $content = $toolCallerFunction->call();
                $thread->messages()->create([
                    'role'    => Message::ROLE_TOOL,
                    'content' => is_string($content) ? $content : json_encode($content),
                    'data'    => [
                        'tool_call_id' => $toolCallerFunction->getId(),
                        'name'         => $toolCallerFunction->getName(),
                    ],
                ]);
            }
            $threadRun->update(['refreshed_at' => now()]);
        } elseif ($response->isFinished()) {
            $lastMessage = $thread->messages()->create([
                'role'    => Message::ROLE_ASSISTANT,
                'content' => $response->getContent(),
            ]);;

            $threadRun->update([
                'status'          => ThreadRun::STATUS_COMPLETED,
                'completed_at'    => now(),
                'input_tokens'    => $response->inputTokens(),
                'output_tokens'   => $response->outputTokens(),
                'last_message_id' => $lastMessage->id,
            ]);
        } else {
            throw new Exception('Unexpected response from AI model');
        }
    }
}
