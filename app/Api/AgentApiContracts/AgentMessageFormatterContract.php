<?php

namespace App\Api\AgentApiContracts;

use App\Models\Agent\AgentThreadMessage;

interface AgentMessageFormatterContract
{
    public function rawMessage(string $role, string $content, array $data = []): array;

    public function message(AgentThreadMessage $message): array;

    public function messageList(array $messages): array;

    public function acceptsJsonSchema(): bool;

    public function wrapMessage(string $prefix, array $message, string $suffix = ''): array;
}
