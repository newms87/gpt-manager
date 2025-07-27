<?php

namespace App\Models;

use App\Models\Team\Team;
use App\Models\TeamObject\TeamObject;
use App\Models\Workflow\WorkflowListener;
use App\Traits\HasWorkflowListeners;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Validator;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class UiDemand extends Model implements Auditable
{
    use HasFactory, SoftDeletes, ActionModelTrait, AuditableTrait, HasWorkflowListeners;

    // Status constants following platform pattern
    const string
        STATUS_DRAFT = 'Draft',
        STATUS_READY = 'Ready',
        STATUS_PROCESSING = 'Processing',
        STATUS_COMPLETED = 'Completed',
        STATUS_FAILED = 'Failed';

    protected $fillable = [
        'team_id',
        'user_id',
        'title',
        'description',
        'status',
        'metadata',
        'team_object_id',
        'submitted_at',
        'completed_at',
    ];

    protected $casts = [
        'metadata'     => 'array',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function validate(array $data = null): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data ?: $this->toArray(), [
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'required|in:' . implode(',', [
                    self::STATUS_DRAFT,
                    self::STATUS_READY,
                    self::STATUS_PROCESSING,
                    self::STATUS_COMPLETED,
                    self::STATUS_FAILED,
                ]),
        ]);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function storedFiles(): MorphToMany
    {
        return $this->morphToMany(StoredFile::class, 'storable', 'stored_file_storables')
            ->withTimestamps();
    }

    public function teamObject(): BelongsTo
    {
        return $this->belongsTo(TeamObject::class);
    }

    public function canBeSubmitted(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->storedFiles()->count() > 0;
    }

    public function submit(): void
    {
        $this->update([
            'status'       => self::STATUS_READY,
            'submitted_at' => now(),
        ]);
    }

    // Specific workflow type helpers
    public function canExtractData(): bool
    {
        return $this->status === self::STATUS_READY && 
            !$this->hasWorkflowOfType(WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA);
    }

    public function canWriteDemand(): bool
    {
        return $this->team_object_id && 
            $this->isWorkflowCompleted(WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA) &&
            !$this->hasWorkflowOfType(WorkflowListener::WORKFLOW_TYPE_WRITE_DEMAND);
    }

    public function isExtractDataRunning(): bool
    {
        return $this->isWorkflowRunning(WorkflowListener::WORKFLOW_TYPE_EXTRACT_DATA);
    }

    public function isWriteDemandRunning(): bool
    {
        return $this->isWorkflowRunning(WorkflowListener::WORKFLOW_TYPE_WRITE_DEMAND);
    }
}
