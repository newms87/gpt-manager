<?php

namespace App\Services\Workflow;

class WorkflowImportService
{
    public function importFromJson(array $workflowDefinitionJson): bool
    {
        dump('import', $workflowDefinitionJson);

        return true;
    }
}
