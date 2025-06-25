<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\TaskArtifactFilterRepository;
use App\Resources\TaskDefinition\TaskArtifactFilterResource;
use Newms87\Danx\Http\Controllers\ActionController;

class TaskArtifactFiltersController extends ActionController
{
    public static ?string $repo     = TaskArtifactFilterRepository::class;
    public static ?string $resource = TaskArtifactFilterResource::class;
}
