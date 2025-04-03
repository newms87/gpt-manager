<?php

namespace App\Resources\TaskDefinition;

use App\Models\Task\TaskDefinitionDirective;
use App\Resources\Prompt\PromptDirectiveResource;
use Newms87\Danx\Resources\ActionResource;

class TaskDefinitionDirectiveResource extends ActionResource
{
    public static function data(TaskDefinitionDirective $taskDefinitionDirective): array
    {
        return [
            'id'        => $taskDefinitionDirective->id,
            'directive' => $taskDefinitionDirective->directive ? PromptDirectiveResource::make($taskDefinitionDirective->directive) : null,
            'position'  => $taskDefinitionDirective->position,
            'section'   => $taskDefinitionDirective->section,
        ];
    }
}
