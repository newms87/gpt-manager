<?php

namespace App\AiTools;

use App\Models\Agent\ThreadRun;

interface AiToolContract
{
    public function execute($params): AiToolResponse;

    public function setThreadRun(ThreadRun $threadRun): static;
}
