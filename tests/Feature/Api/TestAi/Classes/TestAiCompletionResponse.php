<?php

namespace Tests\Feature\Api\TestAi\Classes;

use App\Api\AgentApiContracts\AgentCompletionResponseContract;
use Newms87\Danx\Input\Input;

/**
 * @property string $id       Unique identifier for the object
 * @property string $object   Type of object (ie: chat.completion)
 * @property int    $created  Unix timestamp
 * @property string $model    Model used for completion
 * @property array  $messages Array of completion choices
 * @property array  $usage    Usage statistics for the model (ie: prompt_tokens)
 */
class TestAiCompletionResponse extends Input implements AgentCompletionResponseContract
{
    public function isToolCall(): bool
    {
        return false;
    }

    public function isMessageEmpty(): bool
    {
        return false;
    }

    public function isFinished(): bool
    {
        return true;
    }

    public function getDataFields(): array
    {
        return [];
    }

    public function getToolCallerFunctions(): array
    {
        return [];
    }

    public function getContent(): ?string
    {
        foreach($this->messages as $message) {
            $content = $message['content'] ?? null;
            if (is_array($content)) {
                $content = $content[0]['text'] ?? null;
            }

            if ($content && str_starts_with($content, "Response:")) {
                return str_replace('Response:', '', $content);
            }
        }

        return 'Test AI Response Content';
    }

    public function inputTokens(): int
    {
        return 100000;
    }

    public function outputTokens(): int
    {
        return 500;
    }
}
