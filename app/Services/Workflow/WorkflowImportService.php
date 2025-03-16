<?php

namespace App\Services\Workflow;

class WorkflowImportService
{
    public function importFromJson(string $workflowDefinitionJson): bool
    {
        $data = json_decode($workflowDefinitionJson, true);

        dump('import', $data);

        return true;
    }
}
