<?php

namespace App\Models\Schema;

use App\Models\ResourcePackageableContract;
use App\Services\Workflow\WorkflowExportService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Newms87\Danx\Traits\ActionModelTrait;

class SchemaAssociation extends Model implements ResourcePackageableContract
{
    use HasFactory, ActionModelTrait;

    protected $fillable = [
        'schema_definition_id',
        'schema_fragment_id',
        'category',
    ];

    public function associatedObject(): MorphTo
    {
        return $this->morphTo('object');
    }

    public function schemaDefinition(): BelongsTo|SchemaDefinition
    {
        return $this->belongsTo(SchemaDefinition::class);
    }

    public function schemaFragment(): BelongsTo|SchemaFragment
    {
        return $this->belongsTo(SchemaFragment::class);
    }

    public function exportToJson(WorkflowExportService $service): int
    {
        return $service->register($this, [
            'schema_definition_id' => $service->registerRelatedModel($this->schemaDefinition),
            'schema_fragment_id'   => $service->registerRelatedModel($this->schemaFragment),
            'object_type'          => $this->object_type,
            'object_id'            => $service->registerRelatedModel($this->associatedObject),
            'category'             => $this->category,
        ]);
    }
}
