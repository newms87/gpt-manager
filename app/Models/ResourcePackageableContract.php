<?php

namespace App\Models;

use App\Services\Workflow\WorkflowExportService;

/**
 * @property int id
 */
interface ResourcePackageableContract
{
    public function exportToJson(WorkflowExportService $service): int;
}
