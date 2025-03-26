<?php

namespace App\Models\ResourcePackage;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;

class ResourcePackageImport extends Model implements AuditableContract
{
    use AuditableTrait, SoftDeletes, HasUuids;

    protected $fillable = [
        'team_uuid',
        'resource_package_id',
        'object_type',
        'source_object_id',
    ];

    public function canView(): bool
    {
        return $this->resourcePackage->team_uuid === team()->uuid || $this->can_view;
    }

    public function canEdit(): bool
    {
        return $this->resourcePackage->team_uuid === team()->uuid || $this->can_edit;
    }

    public function resourcePackage(): BelongsTo|ResourcePackage
    {
        return $this->belongsTo(ResourcePackage::class);
    }

    public function resourcePackageVersion(): BelongsTo|ResourcePackageVersion
    {
        return $this->belongsTo(ResourcePackageVersion::class);
    }

    public function getLocalObject(): Model|null
    {
        if (!$this->local_object_id) {
            return null;
        }

        return $this->object_type::find($this->local_object_id);
    }
}
