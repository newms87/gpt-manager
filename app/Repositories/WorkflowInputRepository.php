<?php

namespace App\Repositories;

use App\Models\Workflow\WorkflowInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Repositories\ActionRepository;

class WorkflowInputRepository extends ActionRepository
{
    public static string $model = WorkflowInput::class;

    public function query(): Builder
    {
        return parent::query()->where('team_id', team()->id);
    }

    /**
     * @param string                   $action
     * @param Model|WorkflowInput|null $model
     * @param array|null               $data
     * @return WorkflowInput|bool|mixed|null
     * @throws ValidationError
     */
    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createWorkflowInput($data),
            'update' => $this->updateWorkflowInput($model, $data),
            default => parent::applyAction($action, $model, $data)
        };
    }

    /**
     * @param array $data
     * @return WorkflowInput
     */
    public function createWorkflowInput(array $data): WorkflowInput
    {
        $data['team_id'] = team()->id;
        $data['user_id'] = user()->id;

        $workflowInput = WorkflowInput::make()->forceFill($data)->validate();
        $workflowInput->save();

        $this->syncStoredFiles($workflowInput, $data);

        return $workflowInput;
    }

    /**
     * @param WorkflowInput $workflowInput
     * @param array         $data
     * @return WorkflowInput
     */
    public function updateWorkflowInput(WorkflowInput $workflowInput, array $data): WorkflowInput
    {
        $workflowInput->update($data);
        $this->syncStoredFiles($workflowInput, $data);

        return $workflowInput;
    }

    /**
     * Sync the stored files for the workflow input and set them to be transcoded
     *
     * @param WorkflowInput $workflowInput
     * @param array         $data
     * @return void
     */
    public function syncStoredFiles(WorkflowInput $workflowInput, array $data): void
    {
        if (isset($data['files'])) {
            $files = StoredFile::whereIn('id', collect($data['files'])->pluck('id'))->get();
            $workflowInput->storedFiles()->sync($files);
            $workflowInput->is_transcoded = false;
            $workflowInput->save();
        }
    }
}
