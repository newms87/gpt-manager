<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\WorkflowRunRepository;
use App\Resources\Workflow\WorkflowRunResource;
use Newms87\Danx\Http\Controllers\ActionController;

class WorkflowRunsController extends ActionController
{
    public static string  $repo     = WorkflowRunRepository::class;
    public static ?string $resource = WorkflowRunResource::class;
}
