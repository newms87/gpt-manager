<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\TaskDefinitionRepository;
use App\Resources\TaskDefinition\TaskDefinitionResource;
use Newms87\Danx\Http\Controllers\ActionController;

class TaskDefinitionsController extends ActionController
{
    public static string  $repo     = TaskDefinitionRepository::class;
    public static ?string $resource = TaskDefinitionResource::class;
}
