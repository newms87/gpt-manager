<?php

namespace App\Repositories;

use App\Models\Task\TaskArtifactFilter;
use Newms87\Danx\Repositories\ActionRepository;

class TaskArtifactFilterRepository extends ActionRepository
{
    public static string $model = TaskArtifactFilter::class;
}
