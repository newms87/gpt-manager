<?php

namespace App\Repositories;

use App\Models\Task\TaskInput;
use Newms87\Danx\Repositories\ActionRepository;

class TaskInputRepository extends ActionRepository
{
    public static string $model = TaskInput::class;
}
