<?php

namespace App\Models\Workflow;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class WorkflowConnection extends Model implements AuditableContract
{
    use HasFactory, ActionModelTrait, AuditableTrait;

    protected $fillable = [
        'source_node_id',
        'target_node_id',
        'name',
        'source_output_port',
        'target_input_port',
    ];

    public function sourceNode(): BelongsTo|WorkflowNode
    {
        return $this->belongsTo(WorkflowNode::class, 'source_node_id');
    }

    public function targetNode(): BelongsTo|WorkflowNode
    {
        return $this->belongsTo(WorkflowNode::class, 'target_node_id');
    }

    public function exportToJson(): array
    {
        return [
            'name'               => $this->name,
            'source_node_id'     => $this->source_node_id,
            'target_node_id'     => $this->target_node_id,
            'source_output_port' => $this->source_output_port,
            'target_input_port'  => $this->target_input_port,
        ];
    }

    public function __toString()
    {
        return "<WorkflowConnection id='$this->id' name='$this->name' source-id='$this->source_node_id' target-id='$this->target_node_id'>";
    }
}
