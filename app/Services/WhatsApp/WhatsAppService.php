<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsApp\WhatsAppConnection;
use App\Models\WhatsApp\WhatsAppMessage;
use App\Services\WhatsApp\Providers\TwilioWhatsAppProvider;
use App\Services\WhatsApp\Providers\WhatsAppBusinessProvider;
use App\Services\WhatsApp\Providers\WhatsAppProviderInterface;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected ?WhatsAppProviderInterface $provider = null;

    public function setConnection(WhatsAppConnection $connection): self
    {
        $this->provider = $this->resolveProvider($connection);
        return $this;
    }

    protected function resolveProvider(WhatsAppConnection $connection): WhatsAppProviderInterface
    {
        return match($connection->api_provider) {
            'twilio' => new TwilioWhatsAppProvider($connection),
            'whatsapp_business' => new WhatsAppBusinessProvider($connection),
            default => throw new Exception("Unknown WhatsApp provider: {$connection->api_provider}"),
        };
    }

    public function sendMessage(WhatsAppConnection $connection, string $to, string $message, array $mediaUrls = []): WhatsAppMessage
    {
        $this->setConnection($connection);

        return DB::transaction(function () use ($connection, $to, $message, $mediaUrls) {
            $whatsAppMessage = $connection->messages()->create([
                'from_number' => $connection->phone_number,
                'to_number' => $to,
                'direction' => 'outbound',
                'message' => $message,
                'media_urls' => $mediaUrls ?: null,
                'status' => 'pending',
            ]);

            try {
                $externalId = $this->provider->sendMessage($to, $message, $mediaUrls);
                
                $whatsAppMessage->update([
                    'external_id' => $externalId,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            } catch (Exception $e) {
                $whatsAppMessage->markAsFailed($e->getMessage());
                throw $e;
            }

            return $whatsAppMessage;
        });
    }

    public function receiveMessage(WhatsAppConnection $connection, array $data): WhatsAppMessage
    {
        $this->setConnection($connection);

        return DB::transaction(function () use ($connection, $data) {
            $messageData = $this->provider->parseIncomingMessage($data);

            return $connection->messages()->create([
                'external_id' => $messageData['external_id'],
                'from_number' => $messageData['from'],
                'to_number' => $messageData['to'],
                'direction' => 'inbound',
                'message' => $messageData['message'],
                'media_urls' => $messageData['media_urls'] ?? null,
                'status' => 'received',
                'metadata' => $messageData['metadata'] ?? null,
            ]);
        });
    }

    public function updateMessageStatus(WhatsAppConnection $connection, string $externalId, string $status): void
    {
        $message = $connection->messages()
            ->where('external_id', $externalId)
            ->firstOrFail();

        match($status) {
            'delivered' => $message->markAsDelivered(),
            'read' => $message->markAsRead(),
            'failed' => $message->markAsFailed('Delivery failed'),
            default => Log::warning("Unknown WhatsApp message status: {$status}"),
        };
    }

    public function verifyConnection(WhatsAppConnection $connection): bool
    {
        $this->setConnection($connection);

        try {
            $verified = $this->provider->verifyConnection();
            
            if ($verified) {
                $connection->update([
                    'status' => 'connected',
                    'verified_at' => now(),
                ]);
            } else {
                $connection->update([
                    'status' => 'disconnected',
                    'verified_at' => null,
                ]);
            }

            return $verified;
        } catch (Exception $e) {
            $connection->update([
                'status' => 'error',
                'verified_at' => null,
            ]);
            
            throw $e;
        }
    }

    public function syncMessages(WhatsAppConnection $connection): void
    {
        $this->setConnection($connection);

        $messages = $this->provider->fetchRecentMessages();

        foreach ($messages as $messageData) {
            $existingMessage = $connection->messages()
                ->where('external_id', $messageData['external_id'])
                ->first();

            if (!$existingMessage) {
                $this->receiveMessage($connection, $messageData);
            }
        }

        $connection->update(['last_sync_at' => now()]);
    }

    public function generateWebhookUrl(WhatsAppConnection $connection): string
    {
        return route('webhooks.whatsapp.incoming', [
            'connection' => $connection->id,
            'token' => encrypt($connection->id . '|' . $connection->created_at->timestamp),
        ]);
    }
}