<?php

namespace App\Repositories;

use App\Models\Agent\Message;
use App\Models\Agent\Thread;
use Flytedan\DanxLaravel\Repositories\ActionRepository;

class MessageRepository extends ActionRepository
{
    public static string $model = Message::class;

    public function create(Thread $thread, string $role, array $input = []): Message
    {
        $input += [
            'title' => '(Empty)',
        ];

        return $thread->messages()->create([
                'role' => $role,
            ] + $input);
    }
}
