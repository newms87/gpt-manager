<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\TaskRunRepository;
use App\Resources\TaskDefinition\TaskRunResource;
use Newms87\Danx\Http\Controllers\ActionController;

class TaskRunsController extends ActionController
{
    public static string  $repo     = TaskRunRepository::class;
    public static ?string $resource = TaskRunResource::class;
}
