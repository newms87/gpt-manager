<?php

namespace App\Repositories;

use App\Models\Workflow\WorkflowAssignment;
use Newms87\Danx\Repositories\ActionRepository;

class WorkflowAssignmentRepository extends ActionRepository
{
    public static string $model = WorkflowAssignment::class;
}
