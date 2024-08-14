<?php

namespace App\Api\AgentApiContracts;

use App\AiTools\AiToolResponse;
use App\Models\Agent\Message;

interface AgentMessageFormatterContract
{
    public function rawMessage(string $role, string $content, array $data = []): array;

    public function message(Message $message): array;

    public function messageList(array $messages): array;

    public function acceptsJsonSchema(): bool;

    public function wrapMessage(string $prefix, array $message, string $suffix = ''): array;

    public function toolResponse(string $toolId, string $toolName, AiToolResponse $response): array;
}
