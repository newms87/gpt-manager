<?php

namespace App\Services\Workflow;

use App\Models\Workflow\WorkflowDefinition;
use Illuminate\Database\Eloquent\Model;

class WorkflowExportService
{
    protected array $definitions = [];

    public function exportToJson(WorkflowDefinition $workflowDefinition): array
    {
        $workflowDefinition->exportToJson($this);

        return [
            'version_hash'  => md5(json_encode($this->definitions)),
            'version_date'  => now()->toDateString(),
            'owner_team_id' => $workflowDefinition->team_id,
            'definitions'   => $this->definitions,
        ];
    }

    public function register(Model $model, array $data): int
    {
        $this->definitions[$model::class][$model->id] = $data;

        return $model->id;
    }
}
