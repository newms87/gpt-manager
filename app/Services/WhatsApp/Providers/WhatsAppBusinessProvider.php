<?php

namespace App\Services\WhatsApp\Providers;

use App\Models\WhatsApp\WhatsAppConnection;
use Exception;
use Illuminate\Support\Facades\Http;

class WhatsAppBusinessProvider implements WhatsAppProviderInterface
{
    protected WhatsAppConnection $connection;
    protected string $apiUrl = 'https://graph.facebook.com/v18.0';

    public function __construct(WhatsAppConnection $connection)
    {
        $this->connection = $connection;
        
        if (!$connection->access_token) {
            throw new Exception('WhatsApp Business API access token not configured');
        }
    }

    public function sendMessage(string $to, string $message, array $mediaUrls = []): string
    {
        $phoneNumberId = $this->connection->api_config['phone_number_id'] ?? null;
        
        if (!$phoneNumberId) {
            throw new Exception('WhatsApp Business phone number ID not configured');
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => empty($mediaUrls) ? 'text' : 'media',
        ];

        if (empty($mediaUrls)) {
            $payload['text'] = ['body' => $message];
        } else {
            $payload['type'] = 'image';
            $payload['image'] = [
                'link' => $mediaUrls[0],
                'caption' => $message,
            ];
        }

        $response = Http::withToken($this->connection->access_token)
            ->post("{$this->apiUrl}/{$phoneNumberId}/messages", $payload);

        if (!$response->successful()) {
            throw new Exception('Failed to send WhatsApp message: ' . $response->body());
        }

        $data = $response->json();
        return $data['messages'][0]['id'] ?? '';
    }

    public function parseIncomingMessage(array $data): array
    {
        $message = $data['entry'][0]['changes'][0]['value']['messages'][0] ?? [];
        $contact = $data['entry'][0]['changes'][0]['value']['contacts'][0] ?? [];

        return [
            'external_id' => $message['id'] ?? null,
            'from' => $message['from'] ?? '',
            'to' => $this->connection->phone_number,
            'message' => $message['text']['body'] ?? '',
            'media_urls' => $this->parseMediaFromMessage($message),
            'metadata' => [
                'profile_name' => $contact['profile']['name'] ?? null,
                'message_type' => $message['type'] ?? null,
                'timestamp' => $message['timestamp'] ?? null,
            ],
        ];
    }

    protected function parseMediaFromMessage(array $message): ?array
    {
        if (!isset($message['type']) || $message['type'] === 'text') {
            return null;
        }

        $mediaUrls = [];
        $mediaType = $message['type'];
        
        if (isset($message[$mediaType]['id'])) {
            $mediaUrls[] = $this->fetchMediaUrl($message[$mediaType]['id']);
        }

        return $mediaUrls;
    }

    protected function fetchMediaUrl(string $mediaId): string
    {
        $response = Http::withToken($this->connection->access_token)
            ->get("{$this->apiUrl}/{$mediaId}");

        if ($response->successful()) {
            $data = $response->json();
            return $data['url'] ?? '';
        }

        return '';
    }

    public function verifyConnection(): bool
    {
        $phoneNumberId = $this->connection->api_config['phone_number_id'] ?? null;
        
        if (!$phoneNumberId) {
            return false;
        }

        try {
            $response = Http::withToken($this->connection->access_token)
                ->get("{$this->apiUrl}/{$phoneNumberId}");

            return $response->successful();
        } catch (Exception $e) {
            return false;
        }
    }

    public function fetchRecentMessages(): array
    {
        return [];
    }
}