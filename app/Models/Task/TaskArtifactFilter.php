<?php

namespace App\Models\Task;

use App\Models\ResourcePackage\ResourcePackageableContract;
use App\Models\ResourcePackage\ResourcePackageableTrait;
use App\Models\Schema\SchemaFragment;
use App\Services\Workflow\WorkflowExportService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class TaskArtifactFilter extends Model implements AuditableContract, ResourcePackageableContract
{
    use HasFactory, AuditableTrait, ActionModelTrait, ResourcePackageableTrait;

    protected $fillable = [
        'include_text',
        'include_files',
        'include_json',
        'schema_fragment_id',
    ];

    public function casts(): array
    {
        return [
            'include_text'  => 'boolean',
            'include_files' => 'boolean',
            'include_json'  => 'boolean',
        ];
    }

    public function schemaFragment(): BelongsTo|SchemaFragment
    {
        return $this->belongsTo(SchemaFragment::class);
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
            'schema_fragment_id'        => $this->schema_fragment_id,
        ]);
    }

    public function __toString()
    {
        return "<ArtifactFilter ($this->id) text='$this->include_text' files='$this->include_files' json='$this->include_json'>";
    }
}
