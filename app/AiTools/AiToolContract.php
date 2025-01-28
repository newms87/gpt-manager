<?php

namespace App\AiTools;

use App\Models\Agent\AgentThreadRun;

interface AiToolContract
{
    public function execute($params): AiToolResponse;

    public function setThreadRun(AgentThreadRun $threadRun): static;
}
