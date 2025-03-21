<?php

namespace App\Models\Task;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class TaskArtifactFilter extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, ActionModelTrait;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function casts(): array
    {
        return [
            'include_text'      => 'boolean',
            'include_files'     => 'boolean',
            'include_json'      => 'boolean',
            'fragment_selector' => 'json',
        ];
    }

    public function sourceTaskDefinition(): BelongsTo|TaskDefinition
    {
        return $this->belongsTo(TaskDefinition::class, 'source_task_definition_id');
    }

    public function targetTaskDefinition(): BelongsTo|TaskDefinition
    {
        return $this->belongsTo(TaskDefinition::class, 'target_task_definition_id');
    }

    public function __toString()
    {
        return "<ArtifactFilter ($this->id) text='$this->include_text' files='$this->include_files' json='$this->include_json'>";
    }
}
