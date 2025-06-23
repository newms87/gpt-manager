<?php

namespace App\Repositories;

use App\Models\WhatsApp\WhatsAppMessage;
use Illuminate\Database\Eloquent\Builder;
use Newms87\Danx\Repositories\ActionRepository;

class WhatsAppMessageRepository extends ActionRepository
{
    public static string $model = WhatsAppMessage::class;

    public function query(): Builder
    {
        return parent::query()->whereHas('whatsAppConnection', function ($query) {
            $query->where('team_id', team()->id);
        });
    }

    public function getConversation(int $connectionId, string $phoneNumber, int $limit = 50): Builder
    {
        return $this->query()
            ->where('whatsapp_connection_id', $connectionId)
            ->where(function ($query) use ($phoneNumber) {
                $query->where('from_number', $phoneNumber)
                    ->orWhere('to_number', $phoneNumber);
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit);
    }

    public function getRecentMessages(int $connectionId, int $limit = 20): Builder
    {
        return $this->query()
            ->where('whatsapp_connection_id', $connectionId)
            ->orderBy('created_at', 'desc')
            ->limit($limit);
    }
}