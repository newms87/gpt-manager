<?php

namespace App\Services\WhatsApp\Providers;

interface WhatsAppProviderInterface
{
    public function sendMessage(string $to, string $message, array $mediaUrls = []): string;
    
    public function parseIncomingMessage(array $data): array;
    
    public function verifyConnection(): bool;
    
    public function fetchRecentMessages(): array;
}