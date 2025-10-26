<?php

namespace App\Models\ResourcePackage;

use App\Services\Workflow\WorkflowImportService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;
use Throwable;

class ResourcePackageImport extends Model implements AuditableContract
{
    use AuditableTrait, SoftDeletes, HasUuids;

    protected $fillable = [
        'team_id',
        'creator_team_uuid',
        'resource_package_id',
        'object_type',
        'source_object_id',
    ];

    public function canView(): bool
    {
        return $this->resourcePackage->creator_team_uuid === team()->uuid || $this->can_view;
    }

    public function canEdit(): bool
    {
        return $this->resourcePackage->creator_team_uuid === team()->uuid || $this->can_edit;
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

        try {
            return $this->object_type::find($this->local_object_id);
        } catch (Throwable $throwable) {
            // Handle the case where the object type is not found or any other error
            // This can happen when Models are renamed or removed
            return null;
        }
    }

    /**
     * Attempts to resolve the local object if one was already made matching unique keys defined in the definition
     * NOTE: This is used to avoid trying to create a duplicate record if the local instance already has this resource
     * defined and would throw an error if we tried to create the same object
     */
    public function resolveLocalObjectByUniqueKeys(WorkflowImportService $service, array $definition, bool $isTeam): Model|null
    {
        $table = (new $this->object_type)->getTable();

        // Get the unique keys from the schema definition's index list
        $indexes = Schema::getIndexes($table);

        $uniqueKeys = collect($indexes)->filter(function ($index) {
            return !empty($index['unique']);
        })->pluck('columns')->flatten()->toArray();

        // Now get the values from the $definition based on the keys in the $uniqueKeys list
        $conditions = [];
        foreach ($uniqueKeys as $key) {
            // Skip keys that aren't present in the definition
            if (!isset($definition[$key])) {
                continue;
            }

            $conditions[$key] = $service->resolveDefinitionValue($key, $definition[$key]);
        }

        // If we don't have any unique keys with values, we can't resolve the object
        if (empty($conditions)) {
            return null;
        }

        if ($isTeam) {
            $conditions['team_id'] = team()->id;
        }

        // Try to find an existing object with the same unique key values
        try {
            return $this->object_type::where($conditions)->first();
        } catch (Throwable $throwable) {
            // Handle any errors that might occur (e.g., column doesn't exist)
            return null;
        }
    }
}
