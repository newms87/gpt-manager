<?php

namespace App\Models\ResourcePackage;

use App\Services\Workflow\WorkflowExportService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int    id
 * @property string resource_package_import_id
 */
interface ResourcePackageableContract
{
    public function exportToJson(WorkflowExportService $service): int;

    public function resourcePackageImport(): ResourcePackageImport|BelongsTo;

    public function canView(): bool;

    public function canEdit(): bool;
}
