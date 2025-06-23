<?php

namespace App\Events;

use App\Models\WhatsApp\WhatsAppMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsAppMessageReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public WhatsAppMessage $message
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('team.' . $this->message->whatsAppConnection->team_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'WhatsAppMessageReceived';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'from_number' => $this->message->from_number,
                'to_number' => $this->message->to_number,
                'direction' => $this->message->direction,
                'message' => $this->message->message,
                'status' => $this->message->status,
                'created_at' => $this->message->created_at,
                'connection_id' => $this->message->whatsapp_connection_id,
                'connection_name' => $this->message->whatsAppConnection->name,
            ],
        ];
    }
}