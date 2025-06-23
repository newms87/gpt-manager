<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsAppConnectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone_number' => $this->phone_number,
            'display_phone_number' => $this->getDisplayPhoneNumber(),
            'api_provider' => $this->api_provider,
            'is_active' => $this->is_active,
            'status' => $this->status,
            'webhook_url' => $this->webhook_url,
            'last_sync_at' => $this->last_sync_at,
            'verified_at' => $this->verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'message_count' => $this->message_count ?? 0,
            'has_credentials' => $this->hasCredentials(),
            'is_connected' => $this->isConnected(),
            'api_config' => $this->when(
                $request->user()?->can('view', $this->resource),
                $this->api_config
            ),
        ];
    }
}