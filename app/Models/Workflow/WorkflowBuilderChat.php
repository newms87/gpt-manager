<?php

namespace App\Models\Workflow;

use App\Events\WorkflowBuilderChatUpdatedEvent;
use App\Models\Agent\AgentThread;
use App\Models\Team\Team;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class WorkflowBuilderChat extends Model implements AuditableContract
{
    use HasFactory, ActionModelTrait, AuditableTrait, SoftDeletes;

    // Status constants for enumeration 
    public const STATUS_REQUIREMENTS_GATHERING = 'requirements_gathering';
    public const STATUS_ANALYZING_PLAN = 'analyzing_plan';
    public const STATUS_BUILDING_WORKFLOW = 'building_workflow';
    public const STATUS_EVALUATING_RESULTS = 'evaluating_results';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'workflow_input_id',
        'workflow_definition_id',
        'agent_thread_id', 
        'status',
        'meta',
        'current_workflow_run_id',
        'team_id',
    ];

    public function casts(): array
    {
        return [
            'meta' => 'json',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected $attributes = [
        'status' => self::STATUS_REQUIREMENTS_GATHERING,
        'meta' => '[]',
    ];

    // Relationships
    public function team(): BelongsTo|Team
    {
        return $this->belongsTo(Team::class);
    }

    public function workflowInput(): BelongsTo|WorkflowInput
    {
        return $this->belongsTo(WorkflowInput::class);
    }

    public function workflowDefinition(): BelongsTo|WorkflowDefinition
    {
        return $this->belongsTo(WorkflowDefinition::class);
    }

    public function agentThread(): BelongsTo|AgentThread
    {
        return $this->belongsTo(AgentThread::class);
    }

    public function currentWorkflowRun(): BelongsTo|WorkflowRun
    {
        return $this->belongsTo(WorkflowRun::class, 'current_workflow_run_id');
    }

    public function workflowRuns(): HasMany|WorkflowRun
    {
        return $this->hasMany(WorkflowRun::class, 'id', 'current_workflow_run_id');
    }

    public function workflowInputAssociations(): MorphMany|WorkflowInputAssociation
    {
        return $this->morphMany(WorkflowInputAssociation::class, 'associable');
    }

    // Business Logic Methods

    /**
     * Returns the structured build state from the meta field
     */
    public function getCurrentBuildState(): array
    {
        return $this->meta['build_state'] ?? [];
    }

    /**
     * Updates the current phase and associated meta data
     */
    public function updatePhase(string $phase, array $data = []): static
    {
        $this->validatePhaseTransition($phase);

        $meta = $this->meta ?? [];
        $meta['current_phase'] = $phase;
        $meta['phase_data'] = array_merge($meta['phase_data'] ?? [], $data);
        $meta['updated_at'] = now()->toISOString();

        $this->update([
            'status' => $phase,
            'meta' => $meta,
        ]);

        return $this;
    }

    /**
     * Adds artifacts to meta and broadcasts update event
     */
    public function attachArtifacts(array $artifacts): static
    {
        $meta = $this->meta ?? [];
        $meta['artifacts'] = array_merge($meta['artifacts'] ?? [], $artifacts);
        $meta['artifacts_updated_at'] = now()->toISOString();

        $this->update(['meta' => $meta]);

        WorkflowBuilderChatUpdatedEvent::broadcast($this, 'artifacts', $artifacts);

        return $this;
    }

    /**
     * Adds a message to the associated agent thread and broadcasts event
     */
    public function addThreadMessage(string $message, array $data = []): static
    {
        if (!$this->agentThread) {
            throw new ValidationError('No agent thread associated with this chat', 400);
        }

        // Create the message via the AgentThread relationship
        $agentThreadMessage = $this->agentThread->messages()->create([
            'content' => $message,
            'data' => $data,
            'role' => 'assistant', // Assuming system messages
        ]);

        WorkflowBuilderChatUpdatedEvent::broadcast($this, 'messages', $agentThreadMessage->toArray());

        return $this;
    }

    /**
     * Checks if the chat is waiting for a workflow run to complete
     */
    public function isWaitingForWorkflow(): bool
    {
        return $this->status === self::STATUS_BUILDING_WORKFLOW && 
               $this->currentWorkflowRun && 
               !$this->currentWorkflowRun->isFinished();
    }

    /**
     * Returns the most recent workflow build artifacts from meta
     */
    public function getLatestArtifacts(): array
    {
        return $this->meta['artifacts'] ?? [];
    }

    /**
     * Validate that the phase transition is allowed
     */
    protected function validatePhaseTransition(string $newPhase): void
    {
        $allowedTransitions = [
            self::STATUS_REQUIREMENTS_GATHERING => [
                self::STATUS_ANALYZING_PLAN,
                self::STATUS_BUILDING_WORKFLOW,
                self::STATUS_FAILED,
            ],
            self::STATUS_ANALYZING_PLAN => [
                self::STATUS_REQUIREMENTS_GATHERING,
                self::STATUS_BUILDING_WORKFLOW, 
                self::STATUS_FAILED,
            ],
            self::STATUS_BUILDING_WORKFLOW => [
                self::STATUS_EVALUATING_RESULTS,
                self::STATUS_FAILED,
            ],
            self::STATUS_EVALUATING_RESULTS => [
                self::STATUS_COMPLETED,
                self::STATUS_REQUIREMENTS_GATHERING,
                self::STATUS_FAILED,
            ],
            self::STATUS_COMPLETED => [
                self::STATUS_REQUIREMENTS_GATHERING, // Allow starting new conversation
            ],
            self::STATUS_FAILED => [
                self::STATUS_REQUIREMENTS_GATHERING, // Allow recovery
                self::STATUS_ANALYZING_PLAN,
                self::STATUS_BUILDING_WORKFLOW,
                self::STATUS_EVALUATING_RESULTS,
            ],
        ];

        if (!in_array($newPhase, $allowedTransitions[$this->status] ?? [])) {
            throw new ValidationError(
                "Invalid phase transition from '{$this->status}' to '{$newPhase}'",
                400
            );
        }
    }

    public function validate(): static
    {
        validator($this->toArray(), [
            'workflow_input_id' => ['required', 'integer', 'exists:workflow_inputs,id'],
            'workflow_definition_id' => ['nullable', 'integer', 'exists:workflow_definitions,id'],
            'agent_thread_id' => ['required', 'integer', 'exists:agent_threads,id'],
            'status' => ['required', 'string', 'in:' . implode(',', [
                self::STATUS_REQUIREMENTS_GATHERING,
                self::STATUS_ANALYZING_PLAN,
                self::STATUS_BUILDING_WORKFLOW,
                self::STATUS_EVALUATING_RESULTS,
                self::STATUS_COMPLETED,
                self::STATUS_FAILED,
            ])],
            'current_workflow_run_id' => ['nullable', 'integer', 'exists:workflow_runs,id'],
            'team_id' => ['required', 'integer', 'exists:teams,id'],
        ])->validate();

        return $this;
    }

    public static function booted(): void
    {
        static::saved(function (WorkflowBuilderChat $chat) {
            // Broadcast updates when status or meta changes
            if ($chat->wasChanged(['status', 'meta'])) {
                WorkflowBuilderChatUpdatedEvent::broadcast($chat, 'status_update', [
                    'status' => $chat->status,
                    'meta' => $chat->meta,
                ]);
            }
        });
    }

    public function __toString()
    {
        return "<WorkflowBuilderChat id='{$this->id}' status='{$this->status}' workflow_input_id='{$this->workflow_input_id}'>";
    }
}