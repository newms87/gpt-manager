<?php

namespace App\Repositories;

use App\Models\Workflow\WorkflowAssignment;
use Flytedan\DanxLaravel\Repositories\ActionRepository;

class WorkflowAssignmentRepository extends ActionRepository
{
    public static string $model = WorkflowAssignment::class;
}
