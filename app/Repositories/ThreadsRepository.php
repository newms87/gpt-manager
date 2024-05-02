<?php

namespace App\Repositories;

use App\Models\Agent\Agent;
use App\Models\Agent\Thread;
use App\Models\Agent\ThreadRun;
use Flytedan\DanxLaravel\Exceptions\ValidationError;

class ThreadsRepository
{
    public function create(Agent $agent, $name): Thread
    {
        return Thread::create([
            'team_id'  => user()->team_id,
            'user_id'  => user()->id,
            'name'     => $name,
            'agent_id' => $agent->id,
        ]);
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
