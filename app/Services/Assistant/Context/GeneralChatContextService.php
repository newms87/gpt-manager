<?php

namespace App\Services\Assistant\Context;

use App\Models\Assistant\AssistantAction;

class GeneralChatContextService implements ContextServiceInterface
{
    public function buildSystemPrompt(array $contextData = []): string
    {
        return "You are a helpful AI assistant for the GPT Manager platform. 

Help users with:
- General questions about the platform
- Navigation and feature explanations
- Best practices and recommendations
- Troubleshooting common issues

Be helpful, clear, and concise in your responses. If you don't know something, say so honestly.";
    }

    public function getCapabilities(array $contextData = []): array
    {
        return [
            'answer_questions' => 'Answer general questions about the platform',
            'provide_help' => 'Provide help and guidance',
            'explain_features' => 'Explain platform features and functionality',
        ];
    }

    public function executeAction(AssistantAction $action): array
    {
        return [
            'success' => false,
            'error' => 'General chat context does not support actions',
        ];
    }

    public function canExecuteAction(AssistantAction $action): bool
    {
        return false;
    }
}