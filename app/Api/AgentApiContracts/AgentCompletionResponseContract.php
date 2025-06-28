<?php

namespace App\Api\AgentApiContracts;

interface AgentCompletionResponseContract
{
    public function isMessageEmpty(): bool;

    public function getDataFields(): array;

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

    /**
     * Get the response ID for tracking previous responses in future API calls caching current thread
     */
    public function getResponseId(): string|null;
}
