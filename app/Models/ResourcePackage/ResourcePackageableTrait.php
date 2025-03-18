<?php

namespace App\Models\ResourcePackage;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin ResourcePackageableContract
 * @mixin Model
 * @property BelongsTo|ResourcePackageImport $resourcePackageImport
 */
trait ResourcePackageableTrait
{
    public function resourcePackageImport(): ResourcePackageImport|BelongsTo
    {
        return $this->belongsTo(ResourcePackageImport::class);
    }

    public function canView(): bool
    {
        return $this->resource_package_import_id === null || $this->resourcePackageImport->canView();
    }

    public function canEdit(): bool
    {
        return $this->resource_package_import_id === null;
    }
}
