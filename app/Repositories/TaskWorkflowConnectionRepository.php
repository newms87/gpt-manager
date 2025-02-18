<?php

namespace App\Repositories;

use App\Models\Task\TaskWorkflowConnection;
use Newms87\Danx\Repositories\ActionRepository;

class TaskWorkflowConnectionRepository extends ActionRepository
{
    public static string $model = TaskWorkflowConnection::class;
}
