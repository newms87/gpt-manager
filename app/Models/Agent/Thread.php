<?php

namespace App\Models\Agent;

use App\Models\Team\Team;
use Flytedan\DanxLaravel\Contracts\AuditableContract;
use Flytedan\DanxLaravel\Traits\AuditableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Thread extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, SoftDeletes;

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function runs()
    {
        return $this->hasMany(ThreadRun::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Format the messages to be sent to an AI completion API
     * @return array
     */
    public function getMessagesForApi(): array
    {
        return $this->messages->map(function ($message) {
            return [
                'role'    => $message->role,
                'content' => $message->content,
            ];
        })->toArray();
    }
}
