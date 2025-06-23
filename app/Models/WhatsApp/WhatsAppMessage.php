<?php

namespace App\Models\WhatsApp;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class WhatsAppMessage extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, ActionModelTrait;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'whatsapp_connection_id',
        'external_id',
        'from_number',
        'to_number',
        'direction',
        'message',
        'media_urls',
        'status',
        'metadata',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'error_message',
    ];

    protected $casts = [
        'media_urls'   => 'json',
        'metadata'     => 'json',
        'sent_at'      => 'datetime',
        'delivered_at' => 'datetime',
        'read_at'      => 'datetime',
        'failed_at'    => 'datetime',
    ];

    public function whatsAppConnection(): BelongsTo|WhatsAppConnection
    {
        return $this->belongsTo(WhatsAppConnection::class);
    }

    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent' && $this->sent_at !== null;
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered' && $this->delivered_at !== null;
    }

    public function isRead(): bool
    {
        return $this->status === 'read' && $this->read_at !== null;
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed' && $this->failed_at !== null;
    }

    public function markAsSent(): void
    {
        $this->update([
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        event(new \App\Events\WhatsAppMessageUpdated($this));
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status'       => 'delivered',
            'delivered_at' => now(),
        ]);

        event(new \App\Events\WhatsAppMessageUpdated($this));
    }

    public function markAsRead(): void
    {
        $this->update([
            'status'  => 'read',
            'read_at' => now(),
        ]);

        event(new \App\Events\WhatsAppMessageUpdated($this));
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status'        => 'failed',
            'failed_at'     => now(),
            'error_message' => $errorMessage,
        ]);

        event(new \App\Events\WhatsAppMessageUpdated($this));
    }

    public function getFormattedNumber(string $type = 'from'): string
    {
        $number = $type === 'from' ? $this->from_number : $this->to_number;

        return preg_replace('/(\d{1})(\d{3})(\d{3})(\d{4})/', '+$1 ($2) $3-$4', $number);
    }

    public function hasMedia(): bool
    {
        return !empty($this->media_urls);
    }
}
