<?php

namespace App\Repositories;

use App\Models\Workflow\WorkflowConnection;
use Newms87\Danx\Repositories\ActionRepository;

class WorkflowConnectionRepository extends ActionRepository
{
    public static string $model = WorkflowConnection::class;
}
