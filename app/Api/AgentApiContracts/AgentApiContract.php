<?php

namespace App\Api\AgentApiContracts;

interface AgentApiContract
{
    /**
     * Returns an array of model ID strings
     * @return string[]
     */
    public function getModels(): array;

    /**
     * Completes the messages with the given model and temperature
     *
     * @param string $model
     * @param float  $temperature
     * @param array  $messages
     * @return AgentCompletionResponseContract
     */
    public function complete(string $model, float $temperature, array $messages): AgentCompletionResponseContract;
}
