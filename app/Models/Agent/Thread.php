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
        $messages = collect([
            [
                'role'    => Message::ROLE_USER,
                'content' => $this->agent->prompt,
            ],
        ]);

        foreach($this->messages()->get() as $message) {
            $content = $message->content;
            // If first and last character of the message is a [ and ] or a { and } then json decode the message
            if (in_array(substr($content, 0, 1), ['[', '{']) && in_array(substr($message->content, -1), [']', '}'])) {
                $content = json_decode($content, true);
            }
            $messages->push([
                    'role'    => $message->role,
                    'content' => $content,
                ] + ($message->data ?? []));
        }

        return $messages->toArray();
    }
}
