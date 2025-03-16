<?php

namespace App\Services\Workflow;

use App\Models\CanExportToJsonContract;
use App\Models\Workflow\WorkflowDefinition;

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

    public function register(CanExportToJsonContract $model, array $data): int
    {
        $this->definitions[$model::class][$model->id] = $data;

        return $model->id;
    }

    public function registerRelatedModel(CanExportToJsonContract $model = null): string|null
    {
        if (!$model) {
            return null;
        }

        if (empty($this->definitions[$model::class][$model->id])) {
            $this->definitions[$model::class][$model->id] = true;
            $model->exportToJson($this);
        }

        return $model::class . ':' . $model->id;
    }

    /**
     * Register related models so they are exported.
     * NOTE: These models should associate themselves in their exportToJson() method to the model that has called this
     * method.
     *
     * @param CanExportToJsonContract[] $models
     */
    public function registerRelatedModels($models): void
    {
        foreach($models as $model) {
            if (empty($this->definitions[$model::class][$model->id])) {
                $model->exportToJson($this);
            }
        }
    }
}
