<?php

namespace App\Models\Agent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Models\Job\JobDispatch;
use Newms87\Danx\Traits\AuditableTrait;

class ThreadRun extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, SoftDeletes;

    const string
        STATUS_RUNNING = 'Running',
        STATUS_COMPLETED = 'Completed',
        STATUS_STOPPED = 'Stopped',
        STATUS_FAILED = 'Failed';

    protected $fillable = [
        'completed_at',
        'failed_at',
        'input_tokens',
        'last_message_id',
        'output_tokens',
        'refreshed_at',
        'started_at',
        'status',
        'response_format',
        'seed',
        'temperature',
        'tools',
        'tool_choice',
    ];

    public function casts(): array
    {
        return [
            'tools'        => 'json',
            'started_at'   => 'datetime',
            'completed_at' => 'datetime',
            'failed_at'    => 'datetime',
            'refreshed_at' => 'datetime',
        ];
    }

    public function thread(): Thread|BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function lastMessage(): BelongsTo|Message
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    public function jobDispatch(): BelongsTo|JobDispatch
    {
        return $this->belongsTo(JobDispatch::class);
    }

    public function __toString(): string
    {
        return "<ThreadRun $this->id $this->status thread='{$this->thread->name}'>";
    }
}
