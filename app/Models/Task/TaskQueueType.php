<?php

namespace App\Models\Task;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class TaskQueueType extends Model implements AuditableContract
{
    use ActionModelTrait, AuditableTrait, HasFactory;

    protected $fillable = [
        'name',
        'description',
        'max_workers',
        'queue_name', // For reference/logging only - all TaskProcessJobs run on 'default' queue
        'is_active',
    ];

    protected $casts = [
        'max_workers' => 'integer',
        'is_active'   => 'boolean',
    ];

    public function taskDefinitions(): HasMany
    {
        return $this->hasMany(TaskDefinition::class);
    }

    /**
     * Get the number of currently running workers for this queue type
     */
    public function getRunningWorkersCount(): int
    {
        return $this->taskDefinitions()
            ->join('task_runs', 'task_definitions.id', '=', 'task_runs.task_definition_id')
            ->join('task_processes', 'task_runs.id', '=', 'task_processes.task_run_id')
            ->where('task_processes.status', 'Running')
            ->count();
    }

    /**
     * Check if this queue type has available worker slots
     */
    public function hasAvailableSlots(): bool
    {
        return $this->getRunningWorkersCount() < $this->max_workers;
    }

    /**
     * Get the number of available worker slots
     */
    public function getAvailableSlots(): int
    {
        return max(0, $this->max_workers - $this->getRunningWorkersCount());
    }
}
