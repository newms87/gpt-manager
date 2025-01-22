<?php

namespace App\Models\Task;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;

class TaskProcess extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, HasRelationCountersTrait, SoftDeletes;

    const string
        STATUS_PENDING = 'Pending',
        STATUS_RUNNING = 'Running',
        STATUS_STOPPED = 'Stopped',
        STATUS_COMPLETED = 'Completed',
        STATUS_TIMEOUT = 'Timeout',
        STATUS_FAILED = 'Failed';

    const array STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_RUNNING,
        self::STATUS_COMPLETED,
        self::STATUS_TIMEOUT,
        self::STATUS_FAILED,
    ];

    protected $fillable = [
        'task_step',
        'started_at',
        'stopped_at',
        'completed_at',
        'failed_at',
        'timeout_at',
        'input_tokens',
        'output_tokens',
    ];

    public array $relationCounters = [
        TaskDefinitionAgent::class => ['definitionAgents' => 'task_agent_count'],
    ];

    public function casts(): array
    {
        return [
            'started_at'   => 'datetime',
            'stopped_at'   => 'datetime',
            'completed_at' => 'datetime',
            'failed_at'    => 'datetime',
            'timeout_at'   => 'datetime',
        ];
    }

    public function taskRun(): HasMany|TaskRun
    {
        return $this->hasMany(TaskRun::class);
    }

    public function taskProcessListeners(): HasMany|TaskProcessListener
    {
        return $this->hasMany(TaskProcessListener::class);
    }

    public function computeStatus(): static
    {
        if ($this->started_at === null) {
            $this->status = self::STATUS_PENDING;
        } elseif ($this->failed_at !== null) {
            $this->status = self::STATUS_FAILED;
        } elseif ($this->timeout_at !== null) {
            $this->status = self::STATUS_TIMEOUT;
        } elseif ($this->stopped_at !== null) {
            $this->status = self::STATUS_STOPPED;
        } elseif ($this->completed_at === null) {
            $this->status = self::STATUS_RUNNING;
        } else {
            $this->status = self::STATUS_COMPLETED;
        }

        return $this;
    }

    public static function booted(): void
    {
        static::saving(function (TaskProcess $taskProcess) {
            $taskProcess->computeStatus();
        });
    }

    public function __toString()
    {
        $serviceName = basename($this->task_service);

        return "<TaskRun id='$this->id' name='$this->name' service='$serviceName'>";
    }
}
