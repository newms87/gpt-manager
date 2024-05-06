<?php

namespace App\Api\OpenAi\Classes;

use App\AiTools\AiToolCaller;
use App\Api\AgentApiContracts\AgentCompletionResponseContract;
use Flytedan\DanxLaravel\Input\Input;

/**
 * @property string $id      Unique identifier for the object
 * @property string $object  Type of object (ie: chat.completion)
 * @property int    $created Unix timestamp
 * @property string $model   Model used for completion
 * @property array  $choices Array of completion choices
 * @property array  $usage   Usage statistics for the model (ie: prompt_tokens)
 */
class OpenAiCompletionResponse extends Input implements AgentCompletionResponseContract
{
    public function __construct($items = [])
    {
        parent::__construct($items);
    }

    public function isToolCall(): bool
    {
        return $this->choices[0]['finish_reason'] === 'tool_calls';
    }

    public function isFinished(): bool
    {
        return $this->choices[0]['finish_reason'] === 'stop';
    }

    public function getToolCalls(): array
    {
        $toolCalls = [];
        foreach($this->choices[0]['message']['tool_calls'] as $toolCall) {
            if ($toolCall['type'] === 'function') {
                $function    = $toolCall['function'];
                $toolCalls[] = new AiToolCaller($function['name'], json_decode($function['arguments'], true));
            }
        }

        return $toolCalls;
    }

    public function getMessage(): string
    {
        return $this->choices[0]['message']['content'];
    }

    public function inputTokens(): int
    {
        return $this->usage['prompt_tokens'];
    }

    public function outputTokens(): int
    {
        return $this->usage['completion_tokens'];
    }
}
