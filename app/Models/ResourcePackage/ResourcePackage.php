<?php

namespace App\Models\ResourcePackage;

use App\Models\Team\Team;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;

class ResourcePackage extends Model implements AuditableContract
{
    use AuditableTrait, SoftDeletes, HasUuids;

    protected $fillable = ['id', 'name', 'team_uuid', 'resource_type', 'resource_id'];

    public function team(): BelongsTo|Team
    {
        return $this->belongsTo(Team::class);
    }

    public function resourcePackageVersions(): HasMany|ResourcePackageVersion
    {
        return $this->hasMany(ResourcePackageVersion::class);
    }

    public function getLatestVersion(): ?ResourcePackageVersion
    {
        return $this->resourcePackageVersions()->latest()->first();
    }
}
