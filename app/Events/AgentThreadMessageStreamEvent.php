<?php

namespace App\Events;

use App\Models\Agent\AgentThreadMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentThreadMessageStreamEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        protected AgentThreadMessage $message,
        protected string             $content,
        protected bool               $isComplete = false
    ) {
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('AgentThread.' . $this->message->agentThread->team_id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'message_id'  => $this->message->id,
            'thread_id'   => $this->message->agent_thread_id,
            'content'     => $this->content,
            'is_complete' => $this->isComplete,
            'timestamp'   => now()->toIso8601String(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.stream';
    }
}
