<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\WhatsAppConnectionResource;
use App\Models\WhatsApp\WhatsAppConnection;
use App\Repositories\WhatsAppConnectionRepository;
use App\Services\WhatsApp\WhatsAppService;
use Exception;
use Illuminate\Http\Request;
use Newms87\Danx\Http\Controllers\ActionController;

class WhatsAppConnectionsController extends ActionController
{
    public static string $repo = WhatsAppConnectionRepository::class;
    public static ?string $resource = WhatsAppConnectionResource::class;

    public function verify(WhatsAppConnection $whatsAppConnection)
    {
        try {
            $whatsAppService = app(WhatsAppService::class);
            $verified = $whatsAppService->verifyConnection($whatsAppConnection);
            
            return response()->json([
                'success' => true,
                'verified' => $verified,
                'status' => $whatsAppConnection->fresh()->status,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function generateWebhookUrl(WhatsAppConnection $whatsAppConnection)
    {
        $whatsAppService = app(WhatsAppService::class);
        $webhookUrl = $whatsAppService->generateWebhookUrl($whatsAppConnection);
        
        $whatsAppConnection->update(['webhook_url' => $webhookUrl]);
        
        return response()->json([
            'success' => true,
            'webhook_url' => $webhookUrl,
        ]);
    }

    public function testMessage(Request $request, WhatsAppConnection $whatsAppConnection)
    {
        $request->validate([
            'phone_number' => 'required|string',
            'message' => 'required|string|max:1000',
        ]);

        try {
            $whatsAppService = app(WhatsAppService::class);
            $message = $whatsAppService->sendMessage(
                $whatsAppConnection,
                $request->phone_number,
                $request->message
            );
            
            return response()->json([
                'success' => true,
                'message_id' => $message->id,
                'status' => $message->status,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function syncMessages(WhatsAppConnection $whatsAppConnection)
    {
        try {
            $whatsAppService = app(WhatsAppService::class);
            $whatsAppService->syncMessages($whatsAppConnection);
            
            return response()->json([
                'success' => true,
                'message' => 'Messages synced successfully',
                'last_sync_at' => $whatsAppConnection->fresh()->last_sync_at,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}