<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\TaskInputRepository;
use App\Resources\TaskDefinition\TaskInputResource;
use Newms87\Danx\Http\Controllers\ActionController;

class TaskInputsController extends ActionController
{
    public static string  $repo     = TaskInputRepository::class;
    public static ?string $resource = TaskInputResource::class;
}
