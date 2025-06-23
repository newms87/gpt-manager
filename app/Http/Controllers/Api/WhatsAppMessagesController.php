<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\WhatsAppMessageResource;
use App\Repositories\WhatsAppMessageRepository;
use Illuminate\Http\Request;
use Newms87\Danx\Http\Controllers\ActionController;

class WhatsAppMessagesController extends ActionController
{
    public static string $repo = WhatsAppMessageRepository::class;
    public static ?string $resource = WhatsAppMessageResource::class;

    public function conversation(Request $request)
    {
        $request->validate([
            'connection_id' => 'required|integer|exists:whatsapp_connections,id',
            'phone_number' => 'required|string',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        $messages = app(WhatsAppMessageRepository::class)
            ->getConversation(
                $request->connection_id,
                $request->phone_number,
                $request->limit ?? 50
            )
            ->get();

        return WhatsAppMessageResource::collection($messages);
    }

    public function recent(Request $request)
    {
        $request->validate([
            'connection_id' => 'required|integer|exists:whatsapp_connections,id',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        $messages = app(WhatsAppMessageRepository::class)
            ->getRecentMessages(
                $request->connection_id,
                $request->limit ?? 20
            )
            ->get();

        return WhatsAppMessageResource::collection($messages);
    }
}