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

class WhatsAppMessageUpdated implements ShouldBroadcast
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
        return 'WhatsAppMessageUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'external_id' => $this->message->external_id,
                'from_number' => $this->message->from_number,
                'to_number' => $this->message->to_number,
                'direction' => $this->message->direction,
                'message' => $this->message->message,
                'status' => $this->message->status,
                'sent_at' => $this->message->sent_at,
                'delivered_at' => $this->message->delivered_at,
                'read_at' => $this->message->read_at,
                'failed_at' => $this->message->failed_at,
                'created_at' => $this->message->created_at,
                'updated_at' => $this->message->updated_at,
                'connection_id' => $this->message->whatsapp_connection_id,
                'connection_name' => $this->message->whatsAppConnection->name,
            ],
        ];
    }
}