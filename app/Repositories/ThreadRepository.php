<?php

namespace App\Repositories;

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
            default => parent::applyAction($action, $model, $data)
        };
    }

    public function run(Thread $thread)
    {
        $agent    = $thread->agent;
        $messages = $thread->messages;

        if ($messages->isEmpty()) {
            throw new ValidationError('You must add messages to the thread before running it.');
        }

        $threadRun = $thread->runs()->create([
            'thread_id'       => $thread->id,
            'last_message_id' => $messages->last()->id,
            'status'          => ThreadRun::STATUS_RUNNING,
            'started_at'      => now(),
        ]);

        dump('running thread', $threadRun->toArray());

        $response = $agent->getModelApi()->complete(
            $agent->model,
            $messages->pluck('content')->toArray(),
            $agent->temperature
        );

        dump($response);
    }
}
