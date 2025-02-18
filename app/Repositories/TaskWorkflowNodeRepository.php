<?php

namespace App\Repositories;

use App\Models\Task\TaskWorkflowNode;
use Newms87\Danx\Repositories\ActionRepository;

class TaskWorkflowNodeRepository extends ActionRepository
{
    public static string $model = TaskWorkflowNode::class;
}
