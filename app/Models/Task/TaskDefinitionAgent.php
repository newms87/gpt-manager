<?php

namespace App\Models\Task;

use App\Models\Agent\Agent;
use App\Models\Prompt\PromptSchema;
use App\Models\Prompt\PromptSchemaFragment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;

class TaskDefinitionAgent extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait;

    protected $fillable = [
        'agent_id',
        'include_data',
        'include_text',
        'include_files',
        'input_schema_id',
        'input_schema_fragment_id',
        'output_schema_id',
        'output_schema_fragment_id',
    ];

    public function taskDefinition(): TaskDefinition|BelongsTo
    {
        return $this->belongsTo(TaskDefinition::class);
    }

    public function agent(): BelongsTo|Agent
    {
        return $this->belongsTo(Agent::class);
    }

    public function inputSchema(): BelongsTo|PromptSchema
    {
        return $this->belongsTo(PromptSchema::class, 'input_schema_id');
    }

    public function inputSchemaFragment(): BelongsTo|PromptSchemaFragment
    {
        return $this->belongsTo(PromptSchemaFragment::class, 'input_schema_fragment_id');
    }

    public function outputSchema(): BelongsTo|PromptSchema
    {
        return $this->belongsTo(PromptSchema::class, 'output_schema_id');
    }

    public function outputSchemaFragment(): BelongsTo|PromptSchemaFragment
    {
        return $this->belongsTo(PromptSchemaFragment::class, 'output_schema_fragment_id');
    }

    public function __toString()
    {
        return "<TaskDefinitionAgent id='$this->id' agent-name='{$this->agent->name}'>";
    }
}
