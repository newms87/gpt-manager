<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\TaskProcessRepository;
use App\Resources\TaskDefinition\TaskProcessResource;
use Newms87\Danx\Http\Controllers\ActionController;

class TaskProcessesController extends ActionController
{
    public static string  $repo     = TaskProcessRepository::class;
    public static ?string $resource = TaskProcessResource::class;
}
