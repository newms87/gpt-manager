<?php

namespace App\Services\WhatsApp\Providers;

use App\Models\WhatsApp\WhatsAppConnection;
use Exception;
use Twilio\Rest\Client;

class TwilioWhatsAppProvider implements WhatsAppProviderInterface
{
    protected Client $client;
    protected WhatsAppConnection $connection;

    public function __construct(WhatsAppConnection $connection)
    {
        $this->connection = $connection;
        
        if (!$connection->account_sid || !$connection->auth_token) {
            throw new Exception('Twilio credentials not configured');
        }

        $this->client = new Client($connection->account_sid, $connection->auth_token);
    }

    public function sendMessage(string $to, string $message, array $mediaUrls = []): string
    {
        $params = [
            'from' => 'whatsapp:' . $this->connection->phone_number,
            'body' => $message,
        ];

        if (!empty($mediaUrls)) {
            $params['mediaUrl'] = $mediaUrls;
        }

        $message = $this->client->messages->create(
            'whatsapp:' . $to,
            $params
        );

        return $message->sid;
    }

    public function parseIncomingMessage(array $data): array
    {
        return [
            'external_id' => $data['MessageSid'] ?? null,
            'from' => str_replace('whatsapp:', '', $data['From'] ?? ''),
            'to' => str_replace('whatsapp:', '', $data['To'] ?? ''),
            'message' => $data['Body'] ?? '',
            'media_urls' => $this->parseMediaUrls($data),
            'metadata' => [
                'profile_name' => $data['ProfileName'] ?? null,
                'num_media' => $data['NumMedia'] ?? 0,
            ],
        ];
    }

    protected function parseMediaUrls(array $data): ?array
    {
        $numMedia = (int) ($data['NumMedia'] ?? 0);
        if ($numMedia === 0) {
            return null;
        }

        $mediaUrls = [];
        for ($i = 0; $i < $numMedia; $i++) {
            if (isset($data["MediaUrl{$i}"])) {
                $mediaUrls[] = $data["MediaUrl{$i}"];
            }
        }

        return $mediaUrls;
    }

    public function verifyConnection(): bool
    {
        try {
            $phoneNumber = $this->client->incomingPhoneNumbers
                ->read(['phoneNumber' => $this->connection->phone_number], 1);

            return !empty($phoneNumber);
        } catch (Exception $e) {
            return false;
        }
    }

    public function fetchRecentMessages(): array
    {
        $messages = $this->client->messages->read([
            'to' => 'whatsapp:' . $this->connection->phone_number,
            'dateSentAfter' => now()->subDays(7),
        ], 100);

        $parsedMessages = [];

        foreach ($messages as $message) {
            $parsedMessages[] = [
                'external_id' => $message->sid,
                'from' => str_replace('whatsapp:', '', $message->from),
                'to' => str_replace('whatsapp:', '', $message->to),
                'message' => $message->body,
                'media_urls' => null,
                'metadata' => [
                    'status' => $message->status,
                    'date_sent' => $message->dateSent?->format('Y-m-d H:i:s'),
                ],
            ];
        }

        return $parsedMessages;
    }
}