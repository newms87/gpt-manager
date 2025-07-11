<?php

namespace Tests\Feature\Api\TestAi\Classes;

use App\Api\AgentApiContracts\AgentCompletionResponseContract;
use Newms87\Danx\Input\Input;

/**
 * @property string $id       Unique identifier for the response
 * @property string $status   Status of the response (completed, etc.)
 * @property string $model    Model used for response
 * @property array  $output   Array of response outputs
 * @property array  $usage    Usage statistics for the model (input_tokens, output_tokens)
 */
class TestAiCompletionResponse extends Input implements AgentCompletionResponseContract
{
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

    public function getContent(): ?string
    {
        // Handle Responses API format
        if (isset($this->output[0]['content'])) {
            foreach($this->output[0]['content'] as $content) {
                if (isset($content['type']) && $content['type'] === 'text' && isset($content['text'])) {
                    return $content['text'];
                }
            }
        }

        // Fallback to legacy format for backward compatibility in tests
        foreach($this->get('messages', []) as $message) {
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
        return $this->usage['input_tokens'] ?? 100000;
    }

    public function outputTokens(): int
    {
        return $this->usage['output_tokens'] ?? 500;
    }

    public function getResponseId(): string|null
    {
        return 'test-response-id';
    }

    public function before($value, $strict = false)
    {

    }

    public function after($value, $strict = false)
    {
        
    }
}
