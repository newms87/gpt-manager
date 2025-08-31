<?php

namespace App\Models\Agent;

use App\Events\AgentThreadUpdatedEvent;
use App\Models\Assistant\AssistantAction;
use App\Models\Task\TaskProcess;
use App\Models\Team\Team;
use App\Models\Traits\HasUsageTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class AgentThread extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, SoftDeletes, ActionModelTrait, HasUsageTracking;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    protected array $can = [
        'view' => null,
        'edit' => null,
    ];

    public function can($type = null): bool|array
    {
        if ($this->can['view'] === null) {
            $this->can['view'] = true;
            $this->can['edit'] = true;

            $runs = $this->runs()->whereHas('responseSchema.resourcePackageImport')->with('responseSchema.resourcePackageImport.resourcePackage')->get();

            foreach($runs as $run) {
                if (!$run->responseSchema->canView()) {
                    $this->can['view'] = false;
                }

                if (!$run->responseSchema->canEdit()) {
                    $this->can['edit'] = false;
                }
            }
        }

        return $type ? $this->can[$type] : $this->can;
    }

    public function canView(): bool
    {
        return $this->can('view');
    }

    public function canEdit(): bool
    {
        return $this->can('edit');
    }

    public function team(): BelongsTo|Team
    {
        return $this->belongsTo(Team::class);
    }

    public function runs(): HasMany|AgentThreadRun
    {
        return $this->hasMany(AgentThreadRun::class);
    }

    public function currentRun(): HasOne|AgentThreadRun
    {
        return $this->hasOne(AgentThreadRun::class)->where('status', AgentThreadRun::STATUS_RUNNING);
    }

    public function lastRun(): HasOne|AgentThreadRun
    {
        return $this->hasOne(AgentThreadRun::class)->latest();
    }

    public function taskProcesses(): HasMany|TaskProcess
    {
        return $this->hasMany(TaskProcess::class);
    }

    public function messages(): HasMany|AgentThreadMessage
    {
        return $this->hasMany(AgentThreadMessage::class);
    }

    public function sortedMessages(): HasMany|AgentThreadMessage
    {
        return $this->messages()->orderBy('id');
    }

    public function sortedVisibleMessages(): HasMany|AgentThreadMessage
    {
        return $this->sortedMessages()->where(function ($query) {
            $query->whereNull('data->hidden_from_user')
                ->orWhere('data->hidden_from_user', false);
        });
    }

    /**
     * Get the last message in the thread with an API response ID
     */
    public function getLastTrackedMessageInThread(): AgentThreadMessage|null
    {
        return $this->messages()
            ->whereNotNull('api_response_id')
            ->orderByDesc('created_at')
            ->first();
    }


    /**
     * Get all messages in the thread after the last tracked response
     */
    public function getUnsentMessagesInThread(): array
    {
        $lastTrackedMessage = $this->getLastTrackedMessageInThread();

        if (!$lastTrackedMessage) {
            // No tracked messages, return all messages
            return $this->sortedMessages()->get()->toArray();
        }

        // Return messages created after the last tracked message
        return $this->sortedMessages()
            ->where('id', '>', $lastTrackedMessage->id)
            ->get()
            ->toArray();
    }

    public function agent(): BelongsTo|Agent
    {
        return $this->belongsTo(Agent::class);
    }

    public function assistantActions(): HasMany
    {
        return $this->hasMany(AssistantAction::class)->orderByDesc('created_at');
    }

    public function isRunning(): bool
    {
        return $this->currentRun()->exists();
    }

    public function refreshUsageFromRuns(): void
    {
        $this->aggregateChildUsage('runs');
    }

    public function __toString()
    {
        return "<AgentThread ($this->id) $this->name>";
    }

    public static function booted(): void
    {
        static::saved(function (AgentThread $agentThread) {
            // Broadcast updates when messages are added or thread is updated
            if ($agentThread->wasChanged(['name', 'updated_at']) || $agentThread->wasRecentlyCreated) {
                AgentThreadUpdatedEvent::broadcast($agentThread);
            }
        });
    }
}
