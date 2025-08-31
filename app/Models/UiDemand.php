<?php

namespace App\Models;

use App\Events\UiDemandUpdatedEvent;
use App\Models\Team\Team;
use App\Models\TeamObject\TeamObject;
use App\Models\Traits\HasUsageTracking;
use App\Models\Workflow\WorkflowRun;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Validator;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class UiDemand extends Model implements Auditable
{
    use HasFactory, SoftDeletes, ActionModelTrait, AuditableTrait, HasUsageTracking;

    // Status constants following platform pattern
    const string
        STATUS_DRAFT = 'Draft',
        STATUS_COMPLETED = 'Completed',
        STATUS_FAILED = 'Failed';

    // Workflow type constants
    const string
        WORKFLOW_TYPE_EXTRACT_DATA = 'extract_data',
        WORKFLOW_TYPE_WRITE_DEMAND = 'write_demand';

    protected $fillable = [
        'team_id',
        'user_id',
        'title',
        'description',
        'status',
        'metadata',
        'team_object_id',
        'completed_at',
    ];

    protected $casts = [
        'metadata'     => 'array',
        'completed_at' => 'datetime',
    ];

    public static function booted(): void
    {
        static::saved(function (UiDemand $uiDemand) {
            // Dispatch event when status, metadata, or completion timestamps change
            if ($uiDemand->wasChanged(['status', 'metadata', 'completed_at'])) {
                UiDemandUpdatedEvent::broadcast($uiDemand);
            }
        });
    }

    public function validate(array $data = null): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data ?: $this->toArray(), [
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'required|in:' . implode(',', [
                    self::STATUS_DRAFT,
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

    public function inputFiles(): MorphToMany
    {
        return $this->morphToMany(StoredFile::class, 'storable', 'stored_file_storables')
            ->wherePivot('category', 'input')
            ->withPivot('category')
            ->withTimestamps();
    }

    public function outputFiles(): MorphToMany
    {
        return $this->morphToMany(StoredFile::class, 'storable', 'stored_file_storables')
            ->wherePivot('category', 'output')
            ->withPivot('category')
            ->withTimestamps();
    }

    public function teamObject(): BelongsTo
    {
        return $this->belongsTo(TeamObject::class);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_COMPLETED]);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function workflowRuns(): BelongsToMany
    {
        return $this->belongsToMany(WorkflowRun::class, 'ui_demand_workflow_runs')
            ->withPivot('workflow_type')
            ->withTimestamps();
    }

    // Workflow helpers
    public function canExtractData(): bool
    {
        return $this->status === self::STATUS_DRAFT &&
            $this->inputFiles()->count() > 0 &&
            !$this->isExtractDataRunning();
    }

    public function canWriteDemand(): bool
    {
        // Must have team_object_id and no write demand workflow running
        if (!$this->team_object_id || $this->isWriteDemandRunning()) {
            return false;
        }

        return $this->getLatestExtractDataWorkflowRun()?->isCompleted() ?? false;
    }

    public function isExtractDataRunning(): bool
    {
        return $this->extractDataWorkflowRuns()
            ->whereIn('workflow_runs.status', ['Pending', 'Running'])
            ->exists();
    }

    public function isWriteDemandRunning(): bool
    {
        return $this->writeDemandWorkflowRuns()
            ->whereIn('workflow_runs.status', ['Pending', 'Running'])
            ->exists();
    }

    // Helper methods for workflow runs by type
    public function extractDataWorkflowRuns(): BelongsToMany
    {
        return $this->workflowRuns()
            ->wherePivot('workflow_type', self::WORKFLOW_TYPE_EXTRACT_DATA);
    }

    public function writeDemandWorkflowRuns(): BelongsToMany
    {
        return $this->workflowRuns()
            ->wherePivot('workflow_type', self::WORKFLOW_TYPE_WRITE_DEMAND);
    }

    public function getLatestExtractDataWorkflowRun(): ?WorkflowRun
    {
        // Use preloaded relationships when available for better performance
        if ($this->relationLoaded('workflowRuns')) {
            return $this->workflowRuns
                ->where('pivot.workflow_type', self::WORKFLOW_TYPE_EXTRACT_DATA)
                ->sortByDesc('created_at')
                ->first();
        }

        return $this->extractDataWorkflowRuns()
            ->orderByDesc('created_at')
            ->first();
    }

    public function getLatestWriteDemandWorkflowRun(): ?WorkflowRun
    {
        // Use preloaded relationships when available for better performance
        if ($this->relationLoaded('workflowRuns')) {
            return $this->workflowRuns
                ->where('pivot.workflow_type', self::WORKFLOW_TYPE_WRITE_DEMAND)
                ->sortByDesc('created_at')
                ->first();
        }

        return $this->writeDemandWorkflowRuns()
            ->orderByDesc('created_at')
            ->first();
    }

    public function getExtractDataProgress(): float
    {
        $latestWorkflow = $this->getLatestExtractDataWorkflowRun();

        if (!$latestWorkflow) {
            return 0.0;
        }

        return $latestWorkflow->calculateProgress();
    }

    public function getWriteDemandProgress(): float
    {
        $latestWorkflow = $this->getLatestWriteDemandWorkflowRun();

        if (!$latestWorkflow) {
            return 0.0;
        }

        return $latestWorkflow->calculateProgress();
    }

}
