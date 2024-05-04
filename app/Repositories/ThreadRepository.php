<?php

namespace App\Repositories;

use App\Api\AgentApiContracts\AgentCompletionResponseContract;
use App\Models\Agent\Agent;
use App\Models\Agent\Message;
use App\Models\Agent\Thread;
use App\Models\Agent\ThreadRun;
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
            'last_message_id' => $thread->messages->last()->id,
            'status'          => ThreadRun::STATUS_RUNNING,
            'started_at'      => now(),
        ]);

        $response = $agent->getModelApi()->complete(
            $agent->model,
            $agent->temperature,
            $messages
        );

        $this->handleResponse($thread, $threadRun, $response);

        return $threadRun;
    }

    public function handleResponse(Thread $thread, ThreadRun $threadRun, AgentCompletionResponseContract $response): void
    {
        $threadRun->update([
            'status'        => ThreadRun::STATUS_COMPLETED,
            'completed_at'  => now(),
            'input_tokens'  => $response->inputTokens(),
            'output_tokens' => $response->outputTokens(),
        ]);

        $thread->messages()->create([
            'role'    => Message::ROLE_ASSISTANT,
            'content' => $response->getMessage(),
        ]);
    }
}
