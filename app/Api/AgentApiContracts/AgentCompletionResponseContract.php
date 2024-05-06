<?php

namespace App\Api\AgentApiContracts;

use App\AiTools\AiToolCaller;

interface AgentCompletionResponseContract
{
    public function isToolCall(): bool;

    /**
     * @return array|AiToolCaller[]
     */
    public function getToolCalls(): array;

    /**
     * The completion is finished
     * @return bool
     */
    public function isFinished(): bool;

    /**
     * The message from the agent
     * @return string
     */
    public function getMessage(): string;

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
