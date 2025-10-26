<?php

namespace App\Models\Assistant;

use App\Models\Agent\AgentThread;
use App\Models\Team\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class AssistantAction extends Model implements AuditableContract
{
    use ActionModelTrait, AuditableTrait;

    const STATUS_PENDING     = 'pending',
        STATUS_IN_PROGRESS   = 'in_progress',
        STATUS_COMPLETED     = 'completed',
        STATUS_FAILED        = 'failed',
        STATUS_CANCELLED     = 'cancelled';

    const CONTEXT_SCHEMA_EDITOR    = 'schema-editor',
        CONTEXT_WORKFLOW_EDITOR    = 'workflow-editor',
        CONTEXT_AGENT_MANAGEMENT   = 'agent-management',
        CONTEXT_TASK_MANAGEMENT    = 'task-management',
        CONTEXT_GENERAL_CHAT       = 'general-chat';

    protected $fillable = [
        'team_id',
        'user_id',
        'agent_thread_id',
        'context',
        'action_type',
        'target_type',
        'target_id',
        'status',
        'title',
        'description',
        'payload',
        'preview_data',
        'result_data',
        'error_message',
        'started_at',
        'completed_at',
        'duration',
    ];

    protected $casts = [
        'payload'      => 'array',
        'preview_data' => 'array',
        'result_data'  => 'array',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agentThread(): BelongsTo
    {
        return $this->belongsTo(AgentThread::class);
    }

    public function markInProgress(): void
    {
        $this->update([
            'started_at' => now(),
        ]);
    }

    public function markCompleted(?array $resultData = null): void
    {
        $this->update([
            'completed_at' => now(),
            'result_data'  => $resultData,
        ]);
    }

    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'completed_at'  => now(),
            'error_message' => $errorMessage,
        ]);
    }

    public function markCancelled(): void
    {
        $this->update([
            'completed_at' => now(),
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ]);
    }

    public function authorize(): void
    {
        if ($this->user_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }
    }

    public function computeStatus(): static
    {
        // Compute duration if we have both timestamps
        if ($this->started_at && $this->completed_at) {
            $this->duration = $this->completed_at->diffInSeconds($this->started_at);
        } else {
            $this->duration = null;
        }

        // Compute status based on timestamps and fields
        if ($this->error_message) {
            $this->status = self::STATUS_FAILED;
        } elseif ($this->completed_at && $this->result_data !== null) {
            $this->status = self::STATUS_COMPLETED;
        } elseif ($this->completed_at && $this->result_data === null) {
            $this->status = self::STATUS_CANCELLED;
        } elseif ($this->started_at) {
            $this->status = self::STATUS_IN_PROGRESS;
        } else {
            $this->status = self::STATUS_PENDING;
        }

        return $this;
    }

    public static function booted(): void
    {
        static::saving(function (AssistantAction $action) {
            $action->computeStatus();
        });
    }
}
