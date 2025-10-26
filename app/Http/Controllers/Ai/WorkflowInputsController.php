<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\WorkflowInputRepository;
use App\Resources\Workflow\WorkflowInputResource;
use Newms87\Danx\Http\Controllers\ActionController;

class WorkflowInputsController extends ActionController
{
    public static ?string $repo     = WorkflowInputRepository::class;

    public static ?string $resource = WorkflowInputResource::class;
}
