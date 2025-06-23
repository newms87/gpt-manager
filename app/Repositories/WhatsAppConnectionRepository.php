<?php

namespace App\Repositories;

use App\Models\WhatsApp\WhatsAppConnection;
use Illuminate\Database\Eloquent\Builder;
use Newms87\Danx\Repositories\ActionRepository;

class WhatsAppConnectionRepository extends ActionRepository
{
    public static string $model = WhatsAppConnection::class;

    public function query(): Builder
    {
        return parent::query()->where('team_id', team()->id);
    }

    public function getActiveConnections(): Builder
    {
        return $this->query()
            ->where('is_active', true)
            ->where('status', 'connected');
    }

    public function findByPhoneNumber(string $phoneNumber): ?WhatsAppConnection
    {
        return $this->query()
            ->where('phone_number', $phoneNumber)
            ->first();
    }
}