<?php

namespace App\Services\Task\Runners;

class WorkflowInputTaskRunner extends BaseTaskRunner
{
    const string RUNNER_NAME = 'Workflow Input';
    const bool   IS_TRIGGER  = true;
}
