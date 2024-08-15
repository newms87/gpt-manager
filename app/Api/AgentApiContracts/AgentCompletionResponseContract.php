<?php

namespace App\Api\AgentApiContracts;

use App\AiTools\AiToolCaller;

interface AgentCompletionResponseContract
{
    public function isEmpty(): bool;
    
    public function isToolCall(): bool;

    public function getDataFields(): array;

    /**
     * @return array|AiToolCaller[]
     */
    public function getToolCallerFunctions(): array;

    /**
     * The completion is finished
     * @return bool
     */
    public function isFinished(): bool;

    /**
     * The message from the agent
     * @return ?string
     */
    public function getContent(): ?string;

    /**
     * The number of tokens used for the prompt
     * @return int
     */
    public function inputTokens(): int;

    /**
     * The number of tokens used for the completion response output
     * @return int
     */
    public function outputTokens(): int;
}
