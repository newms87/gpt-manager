<?php

namespace App\Api\AgentApiContracts;

interface AgentCompletionResponseContract
{
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
