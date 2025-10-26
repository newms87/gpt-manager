<?php

namespace App\Api\PerplexityAi\Classes;

use App\Api\OpenAi\Classes\OpenAiMessageFormatter;
use App\Models\Agent\AgentThreadMessage;

class PerplexityAiMessageFormatter extends OpenAiMessageFormatter
{
    public function acceptsJsonSchema(): bool
    {
        return false;
    }

    public function messageList(array $messages): array
    {
        // Merge consecutive user messages into a single message
        $newMessages        = [];
        $prevMessageWasUser = null;

        foreach ($messages as $message) {
            $role    = $message['role'];
            $content = $message['content'];

            if ($prevMessageWasUser && $role === AgentThreadMessage::ROLE_USER) {
                $prevMessage = $newMessages[count($newMessages) - 1]['content'];
                if (!is_array($prevMessage)) {
                    $prevMessage = [['type' => 'text', 'text' => $prevMessage]];
                }
                if (!is_array($content)) {
                    $content = [['type' => 'text', 'text' => $content]];
                }

                $newMessages[count($newMessages) - 1]['content'] = array_merge($prevMessage, $content);
            } else {
                $newMessages[]      = $message;
                $prevMessageWasUser = $role === AgentThreadMessage::ROLE_USER;
            }
        }

        return $newMessages;
    }
}
