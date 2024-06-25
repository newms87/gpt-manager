<?php

namespace App\Repositories;

use App\Models\Agent\Agent;
use App\Models\Agent\Message;
use App\Models\Agent\Thread;
use App\Services\AgentThread\AgentThreadService;
use Illuminate\Database\Eloquent\Builder;
use Newms87\Danx\Helpers\DateHelper;
use Newms87\Danx\Repositories\ActionRepository;

class ThreadRepository extends ActionRepository
{
    public static string $model = Thread::class;

    public function query(): Builder
    {
        return parent::query()->where('team_id', team()->id);
    }

    public function create(Agent $agent, $name = ''): Thread
    {
        if (!$name) {
            $name = $agent->name . " " . DateHelper::formatDateTime(now());
        }

        $thread = Thread::make()->forceFill([
            'team_id'  => team()->id,
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
            'reset-to-message' => $this->resetToMessage($model, $data['message_id']),
            'run' => app(AgentThreadService::class)->run($model),
            'stop' => app(AgentThreadService::class)->stop($model),
            'resume' => app(AgentThreadService::class)->resume($model),
            default => parent::applyAction($action, $model, $data)
        };
    }

    /**
     * Append a new message to the thread
     */
    public function addMessageToThread(Thread $thread, $content = null, ?array $fileIds = null): Thread
    {
        if ($content || $fileIds) {
            $message = $thread->messages()->create([
                'role'    => Message::ROLE_USER,
                'content' => is_string($content) ? $content : json_encode($content),
            ]);

            if ($fileIds) {
                app(MessageRepository::class)->saveFiles($message, $fileIds);
            }
        }

        return $thread;
    }

    /**
     * Deletes all the messages in a thread after the given message
     */
    public function resetToMessage(Thread $thread, $messageId): Thread
    {
        $thread->messages()->where('id', '>', $messageId)->each(fn(Message $m) => $m->delete());

        return $thread;
    }
}
