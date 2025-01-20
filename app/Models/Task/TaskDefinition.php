<?php

namespace App\Models\Task;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;

class TaskDefinition extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, HasRelationCountersTrait, SoftDeletes;

    protected $fillable = [
        'task_service',
        'input_grouping',
        'input_group_chunk_size',
    ];

    public array $relationCounters = [
        TaskDefinitionAgent::class => ['definitionAgents' => 'task_agent_count'],
    ];

    public function casts(): array
    {
        return [
            'input_grouping' => 'json',
        ];
    }

    public function definitionAgents(): HasMany|TaskDefinitionAgent
    {
        return $this->hasMany(TaskDefinitionAgent::class);
    }

    public function __toString()
    {
        $serviceName = basename($this->task_service);

        return "<TaskDefinition id='$this->id' name='$this->name' service='$serviceName'>";
    }
}
