<?php

namespace App\Api\AgentApiContracts;

interface AgentApiContract
{
    /**
     * Retrieve a Message formatter that can convert messages and files into a message structure the API expects
     */
    public function formatter(): AgentMessageFormatterContract;

    /**
     * Completes the messages with the given model and temperature
     *
     * @param string $model
     * @param array  $messages
     * @param array  $options
     * @return AgentCompletionResponseContract
     */
    public function complete(string $model, array $messages, array $options): AgentCompletionResponseContract;
}
