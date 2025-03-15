<?php

namespace App\Repositories;

use App\Models\TeamObject\TeamObject;
use App\Models\Workflow\WorkflowInput;
use Illuminate\Database\Eloquent\Builder;
use Newms87\Danx\Helpers\ModelHelper;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Repositories\ActionRepository;
use Schema;

class WorkflowInputRepository extends ActionRepository
{
    public static string $model = WorkflowInput::class;

    public function query(): Builder
    {
        return parent::query()->where('team_id', team()->id);
    }

    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createWorkflowInput($data),
            'update' => $this->updateWorkflowInput($model, $data),
            default => parent::applyAction($action, $model, $data)
        };
    }

    public function fieldOptions(?array $filter = []): array
    {
        $tags = $this->query()->distinct()->joinRelation('objectTags')->clearGroupBy()
            ->whereHas('objectTags')
            ->select(['objectTags.id as value', 'objectTags.name as label'])->get();

        $options = [
            'tags' => $tags,
        ];

        // If the team objects has been installed, add the object types to the field options
        if (Schema::hasTable((new TeamObject)->getTable())) {
            $options['teamObjectTypes'] = TeamObject::distinct()->select('type')->whereNotNull('schema_definition_id')->whereNull('root_object_id')->get()->pluck('type');
        }

        return $options;
    }

    public function createWorkflowInput(?array $data = []): WorkflowInput
    {
        $data            = $data ?: [];
        $data['team_id'] = team()->id;
        $data['user_id'] = user()->id;
        $data            += [
            'name' => 'New Workflow Input',
        ];

        $workflowInput       = WorkflowInput::make()->forceFill($data);
        $workflowInput->name = ModelHelper::getNextModelName($workflowInput);

        $workflowInput->validate()->save();

        $this->syncStoredFiles($workflowInput, $data);

        return $workflowInput;
    }

    public function updateWorkflowInput(WorkflowInput $workflowInput, array $data): WorkflowInput
    {
        $workflowInput->fill($data)->validate();
        $workflowInput->save($data);
        $this->syncStoredFiles($workflowInput, $data);

        return $workflowInput;
    }

    /**
     * Sync the stored files for the workflow input and set them to be transcoded
     */
    public function syncStoredFiles(WorkflowInput $workflowInput, array $data): void
    {
        if (isset($data['files'])) {
            $files = StoredFile::whereIn('id', collect($data['files'])->pluck('id'))->get();
            $workflowInput->storedFiles()->sync($files);
        }
    }
}
