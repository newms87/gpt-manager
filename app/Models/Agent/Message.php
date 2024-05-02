<?php

namespace App\Models\Agent;

use Flytedan\DanxLaravel\Contracts\AuditableContract;
use Flytedan\DanxLaravel\Traits\AuditableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, SoftDeletes;

    const
        ROLE_USER = 'user',
        ROLE_ASSISTANT = 'assistant';

    protected $fillable = [
        'role',
        'title',
        'summary',
        'content',
    ];
    
    public function thread()
    {
        return $this->belongsTo(Thread::class);
    }
}
