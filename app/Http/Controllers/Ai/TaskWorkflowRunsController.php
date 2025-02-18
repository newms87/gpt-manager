<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\TaskWorkflowRunRepository;
use App\Resources\TaskWorkflow\TaskWorkflowRunResource;
use Newms87\Danx\Http\Controllers\ActionController;

class TaskWorkflowRunsController extends ActionController
{
    public static string  $repo     = TaskWorkflowRunRepository::class;
    public static ?string $resource = TaskWorkflowRunResource::class;
}
