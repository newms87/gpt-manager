<?php

namespace App\Models\Task;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;

class TaskRun extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, HasRelationCountersTrait, SoftDeletes;

    protected $fillable = [
        'started_at',
        'stopped_at',
        'completed_at',
        'failed_at',
        'input_tokens',
        'output_tokens',
    ];

    public array $relationCounters = [
        TaskProcess::class => ['taskProcesses' => 'process_count'],
    ];

    public function casts(): array
    {
        return [
            'started_at'   => 'datetime',
            'stopped_at'   => 'datetime',
            'completed_at' => 'datetime',
            'failed_at'    => 'datetime',
        ];
    }

    public function taskDefinition(): TaskDefinition|BelongsTo
    {
        return $this->belongsTo(TaskDefinition::class);
    }

    public function taskProcesses(): HasMany|TaskProcess
    {
        return $this->hasMany(TaskProcess::class);
    }

    public function computeStatus(): static
    {
        if ($this->started_at === null) {
            $this->status = TaskProcess::STATUS_PENDING;
        } elseif ($this->failed_at !== null) {
            $this->status = TaskProcess::STATUS_FAILED;
        } elseif ($this->stopped_at !== null) {
            $this->status = TaskProcess::STATUS_STOPPED;
        } elseif ($this->completed_at === null) {
            $this->status = TaskProcess::STATUS_RUNNING;
        } else {
            $this->status = TaskProcess::STATUS_COMPLETED;
        }

        return $this;
    }

    public static function booted(): void
    {
        static::saving(function (TaskRun $taskRun) {
            $taskRun->computeStatus();
        });
    }

    public function __toString()
    {
        return "<TaskRun id='$this->id' name='{$this->taskDefinition->name}' processes='$this->process_count'>";
    }
}
