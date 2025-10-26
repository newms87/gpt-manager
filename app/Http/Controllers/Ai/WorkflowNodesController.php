<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\WorkflowNodeRepository;
use App\Resources\Workflow\WorkflowNodeResource;
use Newms87\Danx\Http\Controllers\ActionController;

class WorkflowNodesController extends ActionController
{
    public static ?string $repo     = WorkflowNodeRepository::class;

    public static ?string $resource = WorkflowNodeResource::class;
}
