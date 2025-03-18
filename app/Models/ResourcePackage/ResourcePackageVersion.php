<?php

namespace App\Models\ResourcePackage;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;

class ResourcePackageVersion extends Model implements AuditableContract
{
    use AuditableTrait, SoftDeletes, HasUuids;

    protected $fillable = [
        'id',
        'resource_package_id',
        'version',
        'version_hash',
        'definitions',
    ];

    public function casts()
    {
        return [
            'definitions' => 'json',
        ];
    }

    public function resourcePackage(): BelongsTo|ResourcePackage
    {
        return $this->belongsTo(ResourcePackage::class);
    }

    public function resourcePackageImports(): HasMany|ResourcePackageImport
    {
        return $this->hasMany(ResourcePackageImport::class);
    }
}
