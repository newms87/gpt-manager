<?php

namespace App\Api\OpenAi\Classes;

use App\AiTools\AiToolCaller;
use App\Api\AgentApiContracts\AgentMessageFormatterContract;

class OpenAiToolCaller extends AiToolCaller
{
    public function getFormatter(): AgentMessageFormatterContract
    {
        return app(OpenAiMessageFormatter::class);
    }
}
