<?php

namespace App\Models\Task;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskWorkflowRun extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'started_at',
        'completed_at',
        'failed_at',
    ];

    public function casts(): array
    {
        return [
            'started_at'   => 'timestamp',
            'completed_at' => 'timestamp',
            'failed_at'    => 'timestamp',
        ];
    }

    public function taskWorkflow(): BelongsTo|TaskWorkflow
    {
        return $this->belongsTo(TaskWorkflow::class);
    }

    public function __toString()
    {
        return "<TaskWorkflowRun id='$this->id' status='$this->status'>";
    }
}
