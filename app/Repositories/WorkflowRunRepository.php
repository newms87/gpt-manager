<?php

namespace App\Repositories;

use App\Models\Workflow\WorkflowRun;
use Flytedan\DanxLaravel\Repositories\ActionRepository;

class WorkflowRunRepository extends ActionRepository
{
    public static string $model = WorkflowRun::class;
}
