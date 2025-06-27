<?php

namespace App\Api\AgentApiContracts;

use App\Api\Options\ResponsesApiOptions;
use App\Models\Agent\AgentThreadMessage;

interface AgentApiContract
{
    /**
     * Retrieve a AgentThreadMessage formatter that can convert messages and files into a message structure the API
     * expects
     */
    public function formatter(): AgentMessageFormatterContract;

    /**
     * Execute using the Responses API (if supported by the implementation)
     *
     * @param string              $model
     * @param array               $messages
     * @param ResponsesApiOptions $options
     * @return AgentCompletionResponseContract
     */
    public function responses(string $model, array $messages, ResponsesApiOptions $options): AgentCompletionResponseContract;

    /**
     * Execute streaming Responses API call with real-time message updates
     *
     * @param string              $model
     * @param array               $messages
     * @param ResponsesApiOptions $options
     * @param AgentThreadMessage  $streamMessage Message to populate with stream data
     * @return AgentCompletionResponseContract
     */
    public function streamResponses(string $model, array $messages, ResponsesApiOptions $options, AgentThreadMessage $streamMessage): AgentCompletionResponseContract;
}
