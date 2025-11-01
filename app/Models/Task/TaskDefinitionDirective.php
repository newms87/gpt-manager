<?php

namespace App\Models\Task;

use App\Models\Prompt\PromptDirective;
use App\Models\ResourcePackage\ResourcePackageableContract;
use App\Models\ResourcePackage\ResourcePackageableTrait;
use App\Services\Workflow\WorkflowExportService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class TaskDefinitionDirective extends Model implements AuditableContract, ResourcePackageableContract
{
    use ActionModelTrait, AuditableTrait, ResourcePackageableTrait;

    const string
        SECTION_TOP    = 'Top',
        SECTION_BOTTOM = 'Bottom';

    protected $fillable = [
        'task_definition_id',
        'prompt_directive_id',
        'section',
        'position',
    ];

    public function taskDefinition(): BelongsTo|TaskDefinition
    {
        return $this->belongsTo(TaskDefinition::class);
    }

    public function directive(): BelongsTo|PromptDirective
    {
        return $this->belongsTo(PromptDirective::class, 'prompt_directive_id');
    }

    public function exportToJson(WorkflowExportService $service): int
    {
        return $service->register($this, [
            'task_definition_id'  => $service->registerRelatedModel($this->taskDefinition),
            'prompt_directive_id' => $service->registerRelatedModel($this->directive),
            'section'             => $this->section,
            'position'            => $this->position,
        ]);
    }

    public function __toString(): string
    {
        return "<TaskDefinitionDirective id='$this->id' directive_name='{$this->directive->name}' position='$this->position' section='$this->section'>";
    }
}
