<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\WorkflowConnectionRepository;
use App\Resources\Workflow\WorkflowConnectionResource;
use Newms87\Danx\Http\Controllers\ActionController;

class WorkflowConnectionsController extends ActionController
{
    public static string  $repo     = WorkflowConnectionRepository::class;
    public static ?string $resource = WorkflowConnectionResource::class;
}
