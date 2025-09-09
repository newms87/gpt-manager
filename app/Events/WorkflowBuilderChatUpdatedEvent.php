<?php

namespace App\Events;

use App\Models\Workflow\WorkflowBuilderChat;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkflowBuilderChatUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public WorkflowBuilderChat $chat,
        public string $updateType,
        public array $data = []
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("team.{$this->chat->team_id}.workflow-builder-chat.{$this->chat->id}"),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'chat_id' => $this->chat->id,
            'update_type' => $this->updateType,
            'data' => $this->data,
            'chat' => [
                'id' => $this->chat->id,
                'status' => $this->chat->status,
                'meta' => $this->chat->meta,
                'updated_at' => $this->chat->updated_at,
            ],
        ];
    }

    /**
     * Broadcast the event
     */
    public static function broadcast(WorkflowBuilderChat $chat, string $updateType, array $data = []): void
    {
        event(new static($chat, $updateType, $data));
    }
}