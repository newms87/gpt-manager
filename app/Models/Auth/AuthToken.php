<?php

namespace App\Models\Auth;

use App\Models\Team\Team;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuthToken extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'team_id',
        'service',
        'type',
        'name',
        'access_token',
        'refresh_token',
        'id_token',
        'scopes',
        'expires_at',
        'metadata',
    ];

    protected $casts = [
        'scopes' => 'array',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
        'id_token',
    ];

    // Token types
    public const TYPE_OAUTH = 'oauth';
    public const TYPE_API_KEY = 'api_key';

    // Common services
    public const SERVICE_GOOGLE = 'google';
    public const SERVICE_STRIPE = 'stripe';
    public const SERVICE_OPENAI = 'openai';

    /**
     * Get the team that owns the auth token
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Check if the token is valid (not expired and has access token)
     */
    public function isValid(): bool
    {
        if (empty($this->access_token)) {
            return false;
        }

        // API keys don't expire
        if ($this->type === self::TYPE_API_KEY) {
            return true;
        }

        // OAuth tokens expire
        if ($this->type === self::TYPE_OAUTH) {
            return !$this->isExpired();
        }

        return false;
    }

    /**
     * Check if the OAuth token is expired
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false; // No expiration set
        }

        return now()->isAfter($this->expires_at);
    }

    /**
     * Check if the OAuth token will expire within the given minutes
     */
    public function willExpireWithin(int $minutes): bool
    {
        if (!$this->expires_at) {
            return false; // No expiration set
        }

        return now()->addMinutes($minutes)->isAfter($this->expires_at);
    }

    /**
     * Check if token has a specific scope
     */
    public function hasScope(string $scope): bool
    {
        if (!is_array($this->scopes)) {
            return false;
        }

        return in_array($scope, $this->scopes);
    }

    /**
     * Check if token has all specified scopes
     */
    public function hasScopes(array $scopes): bool
    {
        if (!is_array($this->scopes)) {
            return false;
        }

        return empty(array_diff($scopes, $this->scopes));
    }

    /**
     * Get a human-readable display name for the token
     */
    public function getDisplayName(): string
    {
        if ($this->name) {
            return $this->name;
        }

        return ucfirst($this->service) . ' ' . ucfirst($this->type);
    }

    /**
     * Scope query to specific service
     */
    public function scopeForService($query, string $service)
    {
        return $query->where('service', $service);
    }

    /**
     * Scope query to specific type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope query to team
     */
    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Scope query to valid tokens only
     */
    public function scopeValid($query)
    {
        return $query->where(function ($subQuery) {
            $subQuery->where('type', self::TYPE_API_KEY)
                ->orWhere(function ($oauthQuery) {
                    $oauthQuery->where('type', self::TYPE_OAUTH)
                        ->where(function ($expiryQuery) {
                            $expiryQuery->whereNull('expires_at')
                                ->orWhere('expires_at', '>', now());
                        });
                });
        })->whereNotNull('access_token')->where('access_token', '!=', '');
    }

    /**
     * Get metadata value by key
     */
    public function getMetadata(string $key, $default = null)
    {
        if (!is_array($this->metadata)) {
            return $default;
        }

        return $this->metadata[$key] ?? $default;
    }

    /**
     * Set metadata value by key
     */
    public function setMetadata(string $key, $value): void
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        $metadata[$key] = $value;
        $this->metadata = $metadata;
    }

    /**
     * Check if this token can be refreshed
     */
    public function canBeRefreshed(): bool
    {
        return $this->type === self::TYPE_OAUTH && !empty($this->refresh_token);
    }

    /**
     * Check if this token is likely revoked (expired and no refresh token)
     */
    public function isLikelyRevoked(): bool
    {
        return $this->type === self::TYPE_OAUTH 
            && $this->isExpired() 
            && empty($this->refresh_token);
    }

    /**
     * Mark this token as invalid by soft deleting it
     */
    public function markAsInvalid(): bool
    {
        return $this->delete();
    }

    /**
     * Validation rules for creating/updating auth tokens
     */
    public static function getValidationRules(string $context = 'create'): array
    {
        return [
            'service' => 'required|string|max:50',
            'type' => 'required|in:' . self::TYPE_OAUTH . ',' . self::TYPE_API_KEY,
            'name' => 'nullable|string|max:100',
            'access_token' => 'required|string',
            'refresh_token' => 'nullable|string',
            'id_token' => 'nullable|string',
            'scopes' => 'nullable|array',
            'scopes.*' => 'string',
            'expires_at' => 'nullable|date',
            'metadata' => 'nullable|array',
        ];
    }
}