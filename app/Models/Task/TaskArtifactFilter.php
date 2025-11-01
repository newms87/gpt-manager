<?php

namespace App\Models\Task;

use App\Models\ResourcePackage\ResourcePackageableContract;
use App\Models\ResourcePackage\ResourcePackageableTrait;
use App\Models\Schema\SchemaFragment;
use App\Services\AgentThread\ArtifactFilter;
use App\Services\Workflow\WorkflowExportService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class TaskArtifactFilter extends Model implements AuditableContract, ResourcePackageableContract
{
    use ActionModelTrait, AuditableTrait, HasFactory, ResourcePackageableTrait;

    protected $fillable = [
        'include_text',
        'include_files',
        'include_json',
        'include_meta',
        'schema_fragment_id',
        'meta_fragment_selector',
    ];

    public function casts(): array
    {
        return [
            'include_text'           => 'boolean',
            'include_files'          => 'boolean',
            'include_json'           => 'boolean',
            'include_meta'           => 'boolean',
            'meta_fragment_selector' => 'json',
        ];
    }

    public function schemaFragment(): BelongsTo|SchemaFragment
    {
        return $this->belongsTo(SchemaFragment::class);
    }

    /**
     * Convert this DB-backed filter to a standard ArtifactFilter
     */
    public function toArtifactFilter(): ArtifactFilter
    {
        return new ArtifactFilter(
            includeText: $this->include_text,
            includeFiles: $this->include_files,
            includeJson: $this->include_json,
            includeMeta: $this->include_meta,
            jsonFragmentSelector: $this->schemaFragment?->fragment_selector ?? [],
            metaFragmentSelector: $this->meta_fragment_selector             ?? []
        );
    }

    public function sourceTaskDefinition(): BelongsTo|TaskDefinition
    {
        return $this->belongsTo(TaskDefinition::class, 'source_task_definition_id');
    }

    public function targetTaskDefinition(): BelongsTo|TaskDefinition
    {
        return $this->belongsTo(TaskDefinition::class, 'target_task_definition_id');
    }

    public function exportToJson(WorkflowExportService $service): int
    {
        $service->registerRelatedModels($this->schemaFragment);

        return $service->register($this, [
            'source_task_definition_id' => $service->registerRelatedModel($this->sourceTaskDefinition),
            'target_task_definition_id' => $service->registerRelatedModel($this->targetTaskDefinition),
            'include_text'              => $this->include_text,
            'include_files'             => $this->include_files,
            'include_json'              => $this->include_json,
            'include_meta'              => $this->include_meta,
            'schema_fragment_id'        => $this->schema_fragment_id,
            'meta_fragment_selector'    => $this->meta_fragment_selector,
        ]);
    }

    public function __toString()
    {
        return "<TaskArtifactFilter id='$this->id' text='$this->include_text' files='$this->include_files' json='$this->include_json' meta='$this->include_meta'>";
    }
}
