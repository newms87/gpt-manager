<?php

namespace App\Exceptions\Auth;

use Newms87\Danx\Exceptions\ValidationError;

class NoTokenFoundException extends ValidationError
{
    public function __construct(string $service, ?string $teamId = null, int $code = 404)
    {
        $teamContext = $teamId ? " for team {$teamId}" : '';
        $message     = "No OAuth token found for {$service}{$teamContext}. Please authorize access first.";

        parent::__construct($message, $code);

        // Set additional context for error handling
        $this->context = [
            'service'         => $service,
            'team_id'         => $teamId,
            'error_type'      => 'no_token_found',
            'action_required' => 'oauth_authorization',
        ];
    }

    /**
     * Get service that missing token
     */
    public function getService(): string
    {
        return $this->context['service'] ?? '';
    }

    /**
     * Get team ID that missing token
     */
    public function getTeamId(): ?string
    {
        return $this->context['team_id'] ?? null;
    }

    /**
     * Get the required action to fix this error
     */
    public function getActionRequired(): string
    {
        return $this->context['action_required'] ?? '';
    }
}
