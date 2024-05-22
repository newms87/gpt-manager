<?php

namespace App\Models\Agent;

use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ThreadRun extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, SoftDeletes;

    const
        STATUS_RUNNING = 'Running',
        STATUS_COMPLETED = 'Completed',
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
    ];

    public function casts()
    {
        return [
            'started_at'   => 'datetime',
            'completed_at' => 'datetime',
            'failed_at'    => 'datetime',
            'refreshed_at' => 'datetime',
        ];
    }

    public function thread()
    {
        return $this->belongsTo(Thread::class);
    }

    public function lastMessage()
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }
}
