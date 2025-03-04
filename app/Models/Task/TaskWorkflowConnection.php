<?php

namespace App\Models\Task;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class TaskWorkflowConnection extends Model implements AuditableContract
{
    use HasFactory, ActionModelTrait, AuditableTrait;

    protected $fillable = [
        'source_node_id',
        'target_node_id',
        'name',
        'source_output_port',
        'target_input_port',
    ];

    public function sourceNode(): BelongsTo|TaskWorkflowNode
    {
        return $this->belongsTo(TaskWorkflowNode::class, 'source_node_id');
    }

    public function targetNode(): BelongsTo|TaskWorkflowNode
    {
        return $this->belongsTo(TaskWorkflowNode::class, 'target_node_id');
    }

    public function __toString()
    {
        return "<TaskWorkflowConnection id='$this->id' name='$this->name' source-id='$this->source_node_id' target-id='$this->target_node_id'>";
    }
}
