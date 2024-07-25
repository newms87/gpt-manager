<?php

namespace App\Api\AgentApiContracts;

use App\Models\Agent\Message;

interface AgentMessageFormatterContract
{
    public function rawMessage(string $role, string $content, array $data = []): array;

    public function message(Message $message): array;
}
