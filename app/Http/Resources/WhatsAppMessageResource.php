<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsAppMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'from_number' => $this->from_number,
            'to_number' => $this->to_number,
            'formatted_from_number' => $this->getFormattedNumber('from'),
            'formatted_to_number' => $this->getFormattedNumber('to'),
            'direction' => $this->direction,
            'message' => $this->message,
            'media_urls' => $this->media_urls,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'sent_at' => $this->sent_at,
            'delivered_at' => $this->delivered_at,
            'read_at' => $this->read_at,
            'failed_at' => $this->failed_at,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'is_inbound' => $this->isInbound(),
            'is_outbound' => $this->isOutbound(),
            'has_media' => $this->hasMedia(),
            'whatsapp_connection' => new WhatsAppConnectionResource($this->whenLoaded('whatsAppConnection')),
        ];
    }
}