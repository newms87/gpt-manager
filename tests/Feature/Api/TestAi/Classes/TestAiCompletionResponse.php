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
    protected static ?string $mockResponse = null;

    public static function setMockResponse(?string $response): void
    {
        static::$mockResponse = $response;
    }

    public static function clearMockResponse(): void
    {
        static::$mockResponse = null;
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

    public function getContent(): ?string
    {
        // Return mock response if set
        if (static::$mockResponse !== null) {
            $response = static::$mockResponse;
            static::$mockResponse = null; // Clear after use
            return $response;
        }

        // Handle Responses API format
        $output = $this->get('output');
        if (isset($output[0]['content'])) {
            foreach($output[0]['content'] as $content) {
                if (isset($content['type']) && $content['type'] === 'text' && isset($content['text'])) {
                    return $content['text'];
                }
            }
        }

        return 'Test AI Response Content';
    }

    public function inputTokens(): int
    {
        return $this->get('usage.input_tokens', 100000);
    }

    public function outputTokens(): int
    {
        return $this->get('usage.output_tokens', 500);
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
