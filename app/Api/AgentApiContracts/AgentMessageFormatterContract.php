<?php

namespace App\Api\AgentApiContracts;

interface AgentMessageFormatterContract
{
    public function rawMessage(string $role, string $content, array $data = []): array;

    public function messageList(array $messages): array;

    public function acceptsJsonSchema(): bool;

    public function convertRawMessagesToResponsesApiInput(array $rawMessages): array|string;
}
