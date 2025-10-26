<?php

namespace App\Repositories;

use App\Models\Task\TaskArtifactFilter;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Repositories\ActionRepository;

class TaskArtifactFilterRepository extends ActionRepository
{
    public static string $model = TaskArtifactFilter::class;

    public function applyAction(string $action, Model|array|null $model = null, ?array $data = null)
    {
        match ($action) {
            'create' => $this->create($data),
            default  => parent::applyAction($action, $model, $data)
        };
    }

    public function create(array $input): TaskArtifactFilter
    {
        $sourceTaskDefinitionId = $input['source_task_definition_id'] ?? null;
        $targetTaskDefinitionId = $input['target_task_definition_id'] ?? null;
        $includeText            = $input['include_text']              ?? true;
        $includeFiles           = $input['include_files']             ?? true;
        $includeJson            = $input['include_json']              ?? true;
        $includeMeta            = $input['include_meta']              ?? true;

        $taskArtifactFilter = TaskArtifactFilter::where([
            'source_task_definition_id' => $sourceTaskDefinitionId,
            'target_task_definition_id' => $targetTaskDefinitionId,
        ])->firstOrNew();

        $taskArtifactFilter->forceFill([
            'source_task_definition_id' => $sourceTaskDefinitionId,
            'target_task_definition_id' => $targetTaskDefinitionId,
            'include_text'              => $includeText,
            'include_files'             => $includeFiles,
            'include_json'              => $includeJson,
            'include_meta'              => $includeMeta,
        ]);

        if (!$taskArtifactFilter->sourceTaskDefinition) {
            throw new ValidationError('Source task definition not found');
        }

        if (!$taskArtifactFilter->targetTaskDefinition) {
            throw new ValidationError('Target task definition not found');
        }

        $taskArtifactFilter->save();

        return $taskArtifactFilter;
    }
}
