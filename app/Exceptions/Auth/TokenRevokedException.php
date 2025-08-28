<?php

namespace App\Exceptions\Auth;

use Newms87\Danx\Exceptions\ValidationError;

class TokenRevokedException extends ValidationError
{
    public function __construct(string $service, ?string $teamId = null, ?string $reason = null, int $code = 401)
    {
        $teamContext = $teamId ? " for team {$teamId}" : "";
        $reasonContext = $reason ? " Reason: {$reason}" : "";
        $message = "OAuth token for {$service}{$teamContext} has been revoked or is invalid.{$reasonContext} Re-authorization required.";
        
        parent::__construct($message, $code);
        
        // Set additional context for error handling
        $this->context = [
            'service' => $service,
            'team_id' => $teamId,
            'revoke_reason' => $reason,
            'error_type' => 'token_revoked',
            'action_required' => 'oauth_authorization'
        ];
    }

    /**
     * Get service that has revoked token
     */
    public function getService(): string
    {
        return $this->context['service'] ?? '';
    }

    /**
     * Get team ID that has revoked token
     */
    public function getTeamId(): ?string
    {
        return $this->context['team_id'] ?? null;
    }

    /**
     * Get the reason for token revocation
     */
    public function getRevokeReason(): ?string
    {
        return $this->context['revoke_reason'] ?? null;
    }

    /**
     * Get the required action to fix this error
     */
    public function getActionRequired(): string
    {
        return $this->context['action_required'] ?? '';
    }
}