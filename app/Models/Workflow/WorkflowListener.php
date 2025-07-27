<?php

namespace App\Models\Workflow;

use App\Models\Team\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Validator;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class WorkflowListener extends Model implements AuditableContract
{
    use HasFactory, ActionModelTrait, AuditableTrait;

    // Status constants
    const string
        STATUS_PENDING = 'pending',
        STATUS_RUNNING = 'running',
        STATUS_COMPLETED = 'completed',
        STATUS_FAILED = 'failed';

    // Workflow type constants for UI demands
    const string
        WORKFLOW_TYPE_EXTRACT_DATA = 'extract_data',
        WORKFLOW_TYPE_WRITE_DEMAND = 'write_demand';

    protected $fillable = [
        'team_id',
        'workflow_run_id',
        'listener_type',
        'listener_id',
        'workflow_type',
        'status',
        'metadata',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function validate(array $data = null): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data ?: $this->toArray(), [
            'team_id' => 'required|exists:teams,id',
            'workflow_run_id' => 'required|exists:workflow_runs,id',
            'listener_type' => 'required|string',
            'listener_id' => 'required|integer',
            'workflow_type' => 'required|string|max:255',
            'status' => 'required|in:' . implode(',', [
                self::STATUS_PENDING,
                self::STATUS_RUNNING,
                self::STATUS_COMPLETED,
                self::STATUS_FAILED,
            ]),
        ]);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function workflowRun(): BelongsTo
    {
        return $this->belongsTo(WorkflowRun::class);
    }

    public function listener(): MorphTo
    {
        return $this->morphTo();
    }

    // Status check methods
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED]);
    }

    // Status transition methods
    public function markAsRunning(): self
    {
        $this->update([
            'status' => self::STATUS_RUNNING,
            'started_at' => now(),
            'completed_at' => null,
            'failed_at' => null,
        ]);

        return $this;
    }

    public function markAsCompleted(): self
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'failed_at' => null,
        ]);

        return $this;
    }

    public function markAsFailed(): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'failed_at' => now(),
            'completed_at' => null,
        ]);

        return $this;
    }

    // Query scopes
    public function scopeForListener(Builder $query, string $listenerType, int $listenerId): Builder
    {
        return $query->where('listener_type', $listenerType)
            ->where('listener_id', $listenerId);
    }

    public function scopeForWorkflowType(Builder $query, string $workflowType): Builder
    {
        return $query->where('workflow_type', $workflowType);
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RUNNING);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeFinished(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_COMPLETED, self::STATUS_FAILED]);
    }

    // Static helper methods
    public static function createForListener(
        Model $listener,
        WorkflowRun $workflowRun,
        string $workflowType,
        array $metadata = []
    ): self {
        return static::create([
            'team_id' => $listener->team_id,
            'workflow_run_id' => $workflowRun->id,
            'listener_type' => get_class($listener),
            'listener_id' => $listener->id,
            'workflow_type' => $workflowType,
            'status' => self::STATUS_PENDING,
            'metadata' => $metadata,
        ]);
    }

    public static function findForWorkflowRun(WorkflowRun $workflowRun): ?self
    {
        return static::where('workflow_run_id', $workflowRun->id)->first();
    }

    public static function findForListenerAndType(Model $listener, string $workflowType): ?self
    {
        return static::forListener(get_class($listener), $listener->id)
            ->forWorkflowType($workflowType)
            ->first();
    }

    public function __toString(): string
    {
        return "<WorkflowListener id='$this->id' type='$this->workflow_type' status='$this->status' listener='{$this->listener_type}#{$this->listener_id}'>";
    }
}