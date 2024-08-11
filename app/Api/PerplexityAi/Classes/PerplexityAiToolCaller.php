<?php

namespace App\Api\PerplexityAi\Classes;

use App\AiTools\AiToolCaller;
use App\Api\AgentApiContracts\AgentMessageFormatterContract;

class PerplexityAiToolCaller extends AiToolCaller
{
    public function getFormatter(): AgentMessageFormatterContract
    {
        return app(PerplexityAiMessageFormatter::class);
    }
}
