<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsApp\WhatsAppConnection;
use App\Services\WhatsApp\WhatsAppService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhooksController extends Controller
{
    public function whatsappIncoming(Request $request, string $connection, string $token)
    {
        try {
            $decrypted = decrypt($token);
            [$connectionId, $timestamp] = explode('|', $decrypted);
            
            if ((int) $connectionId !== (int) $connection) {
                return response('Unauthorized', 401);
            }

            $whatsAppConnection = WhatsAppConnection::findOrFail($connection);
            
            if (!$whatsAppConnection->is_active) {
                return response('Connection inactive', 400);
            }

            $whatsAppService = app(WhatsAppService::class);

            if ($request->isMethod('GET')) {
                return $this->handleWebhookVerification($request);
            }

            $data = $request->all();
            Log::info('WhatsApp webhook received', ['data' => $data]);

            if (isset($data['entry'][0]['changes'][0]['value']['messages'])) {
                $message = $whatsAppService->receiveMessage($whatsAppConnection, $data);
                
                event(new \App\Events\WhatsAppMessageReceived($message));
                
                return response('OK', 200);
            }

            if (isset($data['entry'][0]['changes'][0]['value']['statuses'])) {
                foreach ($data['entry'][0]['changes'][0]['value']['statuses'] as $status) {
                    $whatsAppService->updateMessageStatus(
                        $whatsAppConnection,
                        $status['id'],
                        $status['status']
                    );
                }
                
                return response('OK', 200);
            }

            return response('OK', 200);
        } catch (Exception $e) {
            Log::error('WhatsApp webhook error', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);
            
            return response('Error', 500);
        }
    }

    protected function handleWebhookVerification(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('services.whatsapp.verify_token', 'default_token')) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }
}