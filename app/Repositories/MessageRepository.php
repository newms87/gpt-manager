<?php

namespace App\Repositories;

use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadMessage;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Repositories\ActionRepository;

class MessageRepository extends ActionRepository
{
    public static string $model = AgentThreadMessage::class;

    public function create(AgentThread $thread, string $role, array $input = []): AgentThreadMessage
    {
        $input += [
            'title'   => '',
            'content' => '',
        ];

        $message = $thread->messages()->create([
                'role' => $role,
            ] + $input);

        $thread->touch();

        return $message;
    }

    public function saveFiles(AgentThreadMessage $message, $fileIds)
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
