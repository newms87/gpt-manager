<?php

namespace App\Exceptions\Auth;

use Newms87\Danx\Exceptions\ValidationError;

class TokenExpiredException extends ValidationError
{
    public function __construct(string $service, ?string $teamId = null, ?string $expiresAt = null, int $code = 401)
    {
        $teamContext = $teamId ? " for team {$teamId}" : '';
        $expiredAt   = $expiresAt ? " (expired at {$expiresAt})" : '';
        $message     = "OAuth token for {$service}{$teamContext} has expired{$expiredAt}. Token refresh failed - re-authorization required.";

        parent::__construct($message, $code);

        // Set additional context for error handling
        $this->context = [
            'service'         => $service,
            'team_id'         => $teamId,
            'expires_at'      => $expiresAt,
            'error_type'      => 'token_expired',
            'action_required' => 'oauth_authorization',
        ];
    }

    /**
     * Get service that has expired token
     */
    public function getService(): string
    {
        return $this->context['service'] ?? '';
    }

    /**
     * Get team ID that has expired token
     */
    public function getTeamId(): ?string
    {
        return $this->context['team_id'] ?? null;
    }

    /**
     * Get when the token expired
     */
    public function getExpiresAt(): ?string
    {
        return $this->context['expires_at'] ?? null;
    }

    /**
     * Get the required action to fix this error
     */
    public function getActionRequired(): string
    {
        return $this->context['action_required'] ?? '';
    }
}
