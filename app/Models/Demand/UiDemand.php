<?php

namespace App\Models\Demand;

use App\Events\UiDemandUpdatedEvent;
use App\Models\Team\Team;
use App\Models\TeamObject\TeamObject;
use App\Models\Traits\HasUsageTracking;
use App\Models\User;
use App\Models\Workflow\WorkflowRun;
use App\Services\UiDemand\UiDemandWorkflowConfigService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class UiDemand extends Model implements Auditable
{
    use ActionModelTrait, AuditableTrait, HasFactory, HasUsageTracking, SoftDeletes;

    // Status constants following platform pattern
    const string
        STATUS_DRAFT     = 'Draft',
        STATUS_COMPLETED = 'Completed',
        STATUS_FAILED    = 'Failed';

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

    public function validate(?array $data = null): \Illuminate\Contracts\Validation\Validator
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

    public function scopeWithCounts($query)
    {
        return $query->withCount([
            'inputFiles as input_files_count',
            'outputFiles as output_files_count',
        ]);
    }

    public function workflowRuns(): BelongsToMany
    {
        return $this->belongsToMany(WorkflowRun::class, 'ui_demand_workflow_runs')
            ->withPivot('workflow_type')
            ->withTimestamps();
    }

    /**
     * Dynamic workflow methods - work with any workflow defined in config
     */

    /**
     * Check if a workflow can run based on config rules
     */
    public function canRunWorkflow(string $key): bool
    {
        return app(UiDemandWorkflowConfigService::class)->canRunWorkflow($this, $key);
    }

    /**
     * Check if a specific workflow is currently running
     */
    public function isWorkflowRunning(string $key): bool
    {
        return $this->workflowRuns()
            ->wherePivot('workflow_type', $key)
            ->whereIn('workflow_runs.status', ['Pending', 'Running'])
            ->exists();
    }

    /**
     * Get the latest workflow run for a specific workflow key
     */
    public function getLatestWorkflowRun(string $key): ?WorkflowRun
    {
        // Use preloaded relationships when available for better performance
        if ($this->relationLoaded('workflowRuns')) {
            return $this->workflowRuns
                ->where('pivot.workflow_type', $key)
                ->sortByDesc('created_at')
                ->first();
        }

        return $this->workflowRuns()
            ->wherePivot('workflow_type', $key)
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Get all workflow runs for a specific workflow key, sorted by created_at desc
     *
     * @param  string  $key  The workflow config key
     * @return Collection<WorkflowRun>
     */
    public function getWorkflowRunsForKey(string $key): Collection
    {
        // Use preloaded relationships when available for better performance
        if ($this->relationLoaded('workflowRuns')) {
            return $this->workflowRuns
                ->where('pivot.workflow_type', $key)
                ->sortByDesc('created_at')
                ->values(); // Re-index the collection
        }

        return $this->workflowRuns()
            ->wherePivot('workflow_type', $key)
            ->orderByDesc('workflow_runs.created_at')
            ->get();
    }

}
