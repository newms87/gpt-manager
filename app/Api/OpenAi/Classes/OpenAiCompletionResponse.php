<?php

namespace App\Api\OpenAi\Classes;

use App\Api\AgentApiContracts\AgentCompletionResponseContract;
use Newms87\Danx\Input\Input;

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

    public function isMessageEmpty(): bool
    {
        return empty($this->choices[0]['message']['content']);
    }

    public function isToolCall(): bool
    {
        return $this->choices[0]['finish_reason'] === 'tool_calls';
    }

    public function isFinished(): bool
    {
        if ($this->choices[0]['finish_reason'] === 'stop') {
            return true;
        }

        // Check if the is_finished flag was set to true for all tool calls.
        // If so, the completion is finished. If any tools are not finished,
        // the completion is not finished.
        $toolsFinished = false;

        foreach($this->choices[0]['message']['tool_calls'] as $toolCall) {
            if ($toolCall['type'] === 'function') {
                $function  = $toolCall['function'];
                $arguments = json_decode($function['arguments'], true);
                if (empty($arguments['is_finished'])) {
                    return false;
                } else {
                    // If the function is finished, set the flag to true.
                    $toolsFinished = true;
                }
            }
        }

        return $toolsFinished;
    }

    public function getDataFields(): array
    {
        return collect($this->choices[0]['message'])->except(['role', 'content'])->toArray();
    }

    public function getToolCallerFunctions(): array
    {
        $toolCalls = [];
        foreach($this->choices[0]['message']['tool_calls'] as $toolCall) {
            if ($toolCall['type'] === 'function') {
                $function    = $toolCall['function'];
                $toolCalls[] = new OpenAiToolCaller(
                    $toolCall['id'],
                    $function['name'],
                    json_decode($function['arguments'], true)
                );
            }
        }

        return $toolCalls;
    }

    public function getContent(): ?string
    {
        return $this->choices[0]['message']['content'] ?? null;
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
