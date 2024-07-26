<?php

namespace App\Repositories;

use App\Models\Agent\Agent;
use App\Models\Agent\Message;
use App\Models\Agent\Thread;
use App\Services\AgentThread\AgentThreadService;
use Illuminate\Database\Eloquent\Builder;
use Newms87\Danx\Helpers\DateHelper;
use Newms87\Danx\Models\Utilities\StoredFile;
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
            'copy' => $this->copyThread($model),
            'run' => app(AgentThreadService::class)->run($model),
            'stop' => app(AgentThreadService::class)->stop($model),
            'resume' => app(AgentThreadService::class)->resume($model),
            default => parent::applyAction($action, $model, $data)
        };
    }

    /**
     * Append a new message to the thread
     */
    public function addMessageToThread(Thread $thread, string|array|int|bool|null $content = null, array $fileIds = []): Thread
    {
        if ($content || $fileIds) {
            if (is_scalar($content) || !$content) {
                $contentString = (string)$content;
            } else {
                // files is a special key that holds our file IDs
                $storedFiles = $content['files'] ?? [];
                unset($content['files']);

                foreach($storedFiles as $storedFile) {
                    $fileId = $storedFile instanceof StoredFile ? $storedFile->id : ($storedFile['id'] ?? null);
                    if ($fileId) {
                        $fileIds[] = $fileId;
                    }
                }

                // If the content is a single key with a scalar value, just use that as the content string
                if (!empty($content['content']) && count($content) === 1) {
                    $contentString = $content['content'];
                } else {
                    // Otherwise convert the content to a JSON string
                    $contentString = json_encode($content);
                }
            }

            $message = $thread->messages()->create([
                'role'    => Message::ROLE_USER,
                'content' => $contentString,
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

    /**
     * Copy a thread and its messages / files
     */
    public function copyThread(Thread $thread)
    {
        $newThread       = $thread->replicate();
        $newThread->name .= " (Copy)";
        $newThread->save();

        foreach($thread->messages as $message) {
            $messageCopy            = $message->replicate();
            $messageCopy->thread_id = $newThread->id;
            $messageCopy->save();

            foreach($message->storedFiles as $storedFile) {
                $messageCopy->storedFiles()->attach($storedFile);
            }
        }

        return $newThread;
    }
}
