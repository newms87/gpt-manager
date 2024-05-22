<?php

namespace App\Repositories;

use App\Models\Agent\Message;
use App\Models\Agent\Thread;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Repositories\ActionRepository;

class MessageRepository extends ActionRepository
{
    public static string $model = Message::class;

    public function create(Thread $thread, string $role, array $input = []): Message
    {
        $input += [
            'title'   => '',
            'content' => '',
        ];

        return $thread->messages()->create([
                'role' => $role,
            ] + $input);
    }

    public function saveFiles(Message $message, $fileIds)
    {
        $storedFiles = StoredFile::whereIn('id', $fileIds)->get();
        $message->storedFiles()->sync($storedFiles);

        return $message->load('storedFiles');
    }

    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
            'save-files' => $this->saveFiles($model, $data['ids'] ?? []),
            default => parent::applyAction($action, $model, $data)
        };
    }
}
