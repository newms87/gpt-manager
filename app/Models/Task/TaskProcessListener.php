<?php

namespace App\Models\Task;

use App\Models\Workflow\WorkflowRun;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Models\Job\JobDispatch;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class TaskProcessListener extends Model implements AuditableContract
{
    use HasFactory, ActionModelTrait, AuditableTrait;

    static array $allowedEventTypes = [
        WorkflowRun::class,
        TaskRun::class,
        JobDispatch::class,
    ];

    protected $fillable = [
        'event_type',
        'event_id',
        'event_fired_at',
    ];

    public function casts(): array
    {
        return [
            'event_fired_at' => 'datetime',
        ];
    }

    public function taskProcess(): BelongsTo|TaskProcess
    {
        return $this->belongsTo(TaskProcess::class);
    }

    public function getEventObject()
    {
        return $this->event_type::find($this->event_id);
    }

    public function taskProcessListener(): BelongsTo|TaskProcessListener
    {
        return $this->belongsTo(TaskProcessListener::class);
    }

    public function __toString()
    {
        return "<TaskProcessListner id='$this->id' event_type='$this->event_type' event_id='$this->event_id'>";
    }
}
