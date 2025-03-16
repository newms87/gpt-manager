<?php

namespace App\Services\Workflow;

use App\Models\Workflow\WorkflowDefinition;

class ImportExportWorkflowService
{
    public function exportToJson(WorkflowDefinition $workflowDefinition): array
    {
        $data = $workflowDefinition->exportToJson();

        return [
            'version_hash'  => md5(json_encode($data)),
            'version_date'  => now()->toDateString(),
            'owner_team_id' => $workflowDefinition->team_id,
            'definition'    => $data,
        ];
    }

    public function importFromJson(string $workflowDefinitionJson): bool
    {
        $data = json_decode($workflowDefinitionJson, true);

        dump('import', $data);

        return true;
    }
}
