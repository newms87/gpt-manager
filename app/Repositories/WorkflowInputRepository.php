<?php

namespace App\Repositories;

use App\Models\TeamObject\TeamObject;
use App\Models\Workflow\WorkflowInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
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

    public function summaryQuery(array $filter = []): Builder|QueryBuilder
    {
        return parent::summaryQuery($filter)->addSelect([
            DB::raw("SUM(workflow_runs_count) as workflow_runs_count"),
        ]);
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
        if (Schema::hasTable((new TeamObject())->getTable())) {
            $options['teamObjectTypes'] = TeamObject::distinct()->select('type')->get()->pluck('type');
        }

        return $options;
    }

    public function createWorkflowInput(array $data): WorkflowInput
    {
        $data['team_id'] = team()->id;
        $data['user_id'] = user()->id;

        $workflowInput = WorkflowInput::make()->forceFill($data)->validate();
        $workflowInput->save();

        $this->syncStoredFiles($workflowInput, $data);

        return $workflowInput;
    }

    public function updateWorkflowInput(WorkflowInput $workflowInput, array $data): WorkflowInput
    {
        $workflowInput->update($data);
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
