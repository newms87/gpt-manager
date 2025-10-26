<?php

namespace App\Services\Assistant\Context;

use App\Models\Assistant\AssistantAction;

interface ContextServiceInterface
{
    public function buildSystemPrompt(array $contextData = []): string;

    public function getCapabilities(array $contextData = []): array;

    public function executeAction(AssistantAction $action): array;

    public function canExecuteAction(AssistantAction $action): bool;
}
