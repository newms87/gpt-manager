<?php

namespace App\Api\OpenAi\Classes;

use App\Api\AgentApiContracts\AgentCompletionResponseContract;
use Newms87\Danx\Input\Input;

/**
 * @property string $id      Unique identifier for the response (starts with 'resp_')
 * @property string $object  Type of object (ie: response)
 * @property int    $created Unix timestamp
 * @property string $model   Model used for response
 * @property array  $output  Array of response outputs
 * @property array  $usage   Usage statistics for the model
 */
class OpenAiResponsesResponse extends Input implements AgentCompletionResponseContract
{
    public function isMessageEmpty(): bool
    {
        return !$this->getContent();
    }

    public function isFinished(): bool
    {
        // Responses API format - responses are typically finished when returned
        return (bool)$this->output;
    }

    public function getDataFields(): array
    {
        $fields = [];
        
        // Extract MCP tool calls from output
        foreach($this->output as $outputItem) {
            if (($outputItem['type'] ?? '') === 'mcp_call') {
                if (!isset($fields['tool_calls'])) {
                    $fields['tool_calls'] = [];
                }
                
                $fields['tool_calls'][] = [
                    'id' => $outputItem['id'] ?? null,
                    'type' => 'mcp',
                    'function' => [
                        'name' => $outputItem['name'] ?? 'unknown',
                        'arguments' => json_decode($outputItem['arguments'] ?? '{}', true),
                    ],
                    'result' => json_decode($outputItem['output'] ?? '{}', true),
                    'server_label' => $outputItem['server_label'] ?? null,
                ];
            }
        }
        
        // Also extract other data from message content if present
        foreach($this->output as $outputItem) {
            if (($outputItem['type'] ?? '') === 'message' && isset($outputItem['content'])) {
                foreach($outputItem['content'] as $content) {
                    if (isset($content['type']) && $content['type'] !== 'text' && $content['type'] !== 'output_text') {
                        $fields = array_merge($fields, collect($content)->except(['type', 'text'])->toArray());
                    }
                }
            }
        }

        return $fields;
    }

    public function getContent(): ?string
    {
        foreach($this->output as $outputItem) {
            // Handle standard message output
            if (($outputItem['type'] ?? '') === 'message') {
                // Responses API format - output_text type only
                if (isset($outputItem['content'])) {
                    foreach($outputItem['content'] as $content) {
                        if (($content['type'] ?? '') === 'output_text') {
                            return $content['text'] ?? '';
                        }
                    }
                }
            }
            
            // Handle MCP approval requests - return a message indicating tool approval is needed
            if (($outputItem['type'] ?? '') === 'mcp_approval_request') {
                return "MCP tool approval required for: {$outputItem['name']} on {$outputItem['server_label']}";
            }
        }

        return null;
    }

    public function inputTokens(): int
    {
        // Responses API format
        return $this->usage['input_tokens'] ?? 0;
    }

    public function outputTokens(): int
    {
        // Responses API format
        return $this->usage['output_tokens'] ?? 0;
    }

    /**
     * Get the response ID for tracking previous responses in future API calls
     * For Responses API, this is the 'id' field that starts with 'resp_'
     */
    public function getResponseId(): string|null
    {
        return $this->id;
    }
}
