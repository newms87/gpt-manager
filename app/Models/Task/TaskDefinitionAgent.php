<?php

namespace App\Models\Task;

use App\Models\Agent\Agent;
use App\Models\ResourcePackage\ResourcePackageableContract;
use App\Models\ResourcePackage\ResourcePackageableTrait;
use App\Models\Schema\SchemaAssociation;
use App\Models\Schema\SchemaDefinition;
use App\Services\Workflow\WorkflowExportService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Helpers\ArrayHelper;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class TaskDefinitionAgent extends Model implements AuditableContract, ResourcePackageableContract
{
    use ActionModelTrait, HasFactory, AuditableTrait, ResourcePackageableTrait;

    protected $fillable = [
        'agent_id',
        'include_data',
        'include_text',
        'include_files',
    ];

    public function taskDefinition(): TaskDefinition|BelongsTo
    {
        return $this->belongsTo(TaskDefinition::class);
    }

    public function agent(): BelongsTo|Agent
    {
        return $this->belongsTo(Agent::class);
    }

    public function schemaAssociations(): MorphMany|SchemaAssociation
    {
        return $this->morphMany(SchemaAssociation::class, 'object');
    }

    public function inputSchemaAssociations(): MorphMany|SchemaDefinition
    {
        return $this->schemaAssociations()->where('category', 'input');
    }

    public function outputSchemaAssociation(): MorphOne|SchemaAssociation
    {
        return $this->morphOne(SchemaAssociation::class, 'object')->where('category', 'output');
    }

    /**
     * Get the fragment selector for the input schema. This merges all input fragments together to resolve to a single
     * fragment selector
     */
    public function getInputFragmentSelector(): array
    {
        $fragmentSelector = [];
        foreach($this->inputSchemaAssociations as $inputSchemaAssociation) {
            $fragmentSelector = ArrayHelper::mergeArraysRecursively($inputSchemaAssociation->schemaFragment->fragment_selector, $fragmentSelector);
        }

        return $fragmentSelector;
    }

    public function exportToJson(WorkflowExportService $service): int
    {
        $service->registerRelatedModels($this->schemaAssociations);

        return $service->register($this, [
            'include_text'       => $this->include_text,
            'include_files'      => $this->include_files,
            'include_data'       => $this->include_data,
            'task_definition_id' => $service->registerRelatedModel($this->taskDefinition),
            'agent_id'           => $service->registerRelatedModel($this->agent),
        ]);
    }

    public function __toString()
    {
        return "<TaskDefinitionAgent id='$this->id' agent-name='{$this->agent->name}' include-data='$this->include_data' include-files='$this->include_files' include-text='$this->include_text'>";
    }
}
