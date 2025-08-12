<?php

namespace App\Services\Auth;

use App\Models\Auth\AuthToken;
use App\Models\Team\Team;
use App\Repositories\Auth\AuthTokenRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Exceptions\ValidationError;

class OAuthService
{
    protected AuthTokenRepository $repository;

    public function __construct(AuthTokenRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Check if OAuth is configured for a service
     */
    public function isConfigured(string $service): bool
    {
        $config = $this->getServiceConfig($service);
        
        return !empty($config['client_id']) && !empty($config['client_secret']);
    }

    /**
     * Check if a team has a valid OAuth token for a service
     */
    public function hasValidToken(string $service, ?Team $team = null): bool
    {
        $token = $this->repository->getOAuthToken($service, $team);
        
        return $token && $token->isValid();
    }

    /**
     * Check if a team has a valid OAuth token with required scopes for a service
     */
    public function hasValidTokenWithScopes(string $service, array $requiredScopes, ?Team $team = null): bool
    {
        $token = $this->repository->getOAuthToken($service, $team);
        
        return $token && $token->isValid() && $token->hasScopes($requiredScopes);
    }

    /**
     * Get OAuth token for a service and team
     */
    public function getToken(string $service, ?Team $team = null): ?AuthToken
    {
        $token = $this->repository->getOAuthToken($service, $team);

        if (!$token) {
            return null;
        }

        // If token will expire within 5 minutes, try to refresh it
        if ($token->willExpireWithin(5)) {
            try {
                $token = $this->refreshToken($service, $token);
            } catch (\Exception $e) {
                Log::warning('OAuthService: Failed to refresh token', [
                    'service' => $service,
                    'team_id' => $token->team_id,
                    'error' => $e->getMessage()
                ]);
                // Return the existing token even if refresh failed
                // The caller can handle expired tokens
            }
        }

        return $token;
    }

    /**
     * Generate the OAuth authorization URL for a service
     */
    public function getAuthorizationUrl(string $service, ?string $state = null, ?Team $team = null): string
    {
        $this->validateServiceConfiguration($service);
        $config = $this->getServiceConfig($service);

        // If team is provided, encode team ID in state
        if ($team && !$state) {
            $state = base64_encode(json_encode([
                'service' => $service,
                'team_id' => $team->id,
                'timestamp' => time()
            ]));
        }

        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect_uri'],
            'scope' => is_array($config['scopes']) ? implode(' ', $config['scopes']) : $config['scopes'],
            'response_type' => 'code',
            'access_type' => $config['access_type'] ?? 'offline',
        ];

        // Service-specific parameters
        if (isset($config['approval_prompt'])) {
            $params['approval_prompt'] = $config['approval_prompt'];
        }

        if ($state) {
            $params['state'] = $state;
        }

        $url = $config['auth_url'] . '?' . http_build_query($params);

        Log::info('OAuthService: Generated authorization URL', [
            'service' => $service,
            'redirect_uri' => $params['redirect_uri'],
            'scopes' => $params['scope']
        ]);

        return $url;
    }

    /**
     * Exchange authorization code for access token
     */
    public function exchangeCodeForToken(string $service, string $code): array
    {
        $this->validateServiceConfiguration($service);
        $config = $this->getServiceConfig($service);

        try {
            Log::info('OAuthService: Exchanging authorization code for token', [
                'service' => $service,
                'code_length' => strlen($code)
            ]);

            $response = Http::asForm()->post($config['token_url'], [
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'redirect_uri' => $config['redirect_uri'],
                'grant_type' => 'authorization_code',
                'code' => $code,
            ]);

            if (!$response->successful()) {
                $error = $response->json();
                Log::error('OAuthService: Failed to exchange code for token', [
                    'service' => $service,
                    'status' => $response->status(),
                    'error' => $error
                ]);
                throw new ValidationError('Failed to exchange authorization code: ' . ($error['error_description'] ?? 'Unknown error'), 400);
            }

            $tokenData = $response->json();

            if (!isset($tokenData['access_token'])) {
                throw new ValidationError('Invalid token response - missing access_token', 400);
            }

            Log::info('OAuthService: Successfully exchanged code for token', [
                'service' => $service,
                'expires_in' => $tokenData['expires_in'] ?? null,
                'scope' => $tokenData['scope'] ?? null
            ]);

            return $tokenData;

        } catch (\Exception $e) {
            if ($e instanceof ValidationError) {
                throw $e;
            }

            Log::error('OAuthService: Exception during token exchange', [
                'service' => $service,
                'error' => $e->getMessage()
            ]);

            throw new ValidationError('Failed to exchange authorization code: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Store OAuth token for a team
     */
    public function storeToken(string $service, array $tokenData, ?Team $team = null, array $metadata = []): AuthToken
    {
        $this->validateTokenData($tokenData);

        $token = $this->repository->storeOAuthToken($service, $tokenData, $team, $metadata);

        Log::info('OAuthService: OAuth token stored successfully', [
            'service' => $service,
            'team_id' => $token->team_id,
            'expires_at' => $token->expires_at?->toISOString()
        ]);

        return $token;
    }

    /**
     * Refresh an expired or expiring OAuth token
     */
    public function refreshToken(string $service, AuthToken $token): AuthToken
    {
        $this->validateServiceConfiguration($service);
        $this->validateTokenOwnership($token);
        $config = $this->getServiceConfig($service);

        if ($token->service !== $service) {
            throw new ValidationError('Token service mismatch', 400);
        }

        if (!$token->refresh_token) {
            throw new ValidationError('No refresh token available', 400);
        }

        try {
            Log::info('OAuthService: Refreshing OAuth token', [
                'service' => $service,
                'team_id' => $token->team_id,
                'expires_at' => $token->expires_at?->toISOString()
            ]);

            $response = Http::asForm()->post($config['token_url'], [
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'refresh_token' => $token->refresh_token,
                'grant_type' => 'refresh_token',
            ]);

            if (!$response->successful()) {
                $error = $response->json();
                Log::error('OAuthService: Failed to refresh token', [
                    'service' => $service,
                    'status' => $response->status(),
                    'error' => $error,
                    'team_id' => $token->team_id
                ]);

                // If refresh token is invalid, delete the stored token
                if (isset($error['error']) && in_array($error['error'], ['invalid_grant', 'invalid_request'])) {
                    $this->repository->revokeToken($token);
                    throw new ValidationError('OAuth token has been revoked. Re-authorization required.', 401);
                }

                throw new ValidationError('Failed to refresh OAuth token: ' . ($error['error_description'] ?? 'Unknown error'), 400);
            }

            $tokenData = $response->json();

            if (!isset($tokenData['access_token'])) {
                throw new ValidationError('Invalid token refresh response - missing access_token', 400);
            }

            $token = $this->repository->updateOAuthToken($token, $tokenData);

            Log::info('OAuthService: Successfully refreshed OAuth token', [
                'service' => $service,
                'team_id' => $token->team_id,
                'new_expires_at' => $token->expires_at?->toISOString()
            ]);

            return $token;

        } catch (\Exception $e) {
            if ($e instanceof ValidationError) {
                throw $e;
            }

            Log::error('OAuthService: Exception during token refresh', [
                'service' => $service,
                'team_id' => $token->team_id,
                'error' => $e->getMessage()
            ]);

            throw new ValidationError('Failed to refresh OAuth token: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Revoke OAuth token for a service and team
     */
    public function revokeToken(string $service, ?Team $team = null): bool
    {
        $token = $this->repository->getOAuthToken($service, $team);

        if (!$token) {
            return false;
        }

        return $this->revokeSpecificToken($service, $token);
    }

    /**
     * Revoke a specific OAuth token
     */
    public function revokeSpecificToken(string $service, AuthToken $token): bool
    {
        $this->validateTokenOwnership($token);
        $config = $this->getServiceConfig($service);

        try {
            Log::info('OAuthService: Revoking OAuth token', [
                'service' => $service,
                'team_id' => $token->team_id
            ]);

            // Revoke the token with the service if revoke URL is configured
            if (isset($config['revoke_url'])) {
                $response = Http::asForm()->post($config['revoke_url'], [
                    'token' => $token->refresh_token ?: $token->access_token,
                ]);

                if (!$response->successful()) {
                    Log::warning('OAuthService: Failed to revoke token with service (will delete locally)', [
                        'service' => $service,
                        'team_id' => $token->team_id,
                        'status' => $response->status()
                    ]);
                }
            }

            // Delete the local token regardless of service response
            $this->repository->revokeToken($token);

            Log::info('OAuthService: Successfully revoked OAuth token', [
                'service' => $service,
                'team_id' => $token->team_id
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('OAuthService: Exception during token revocation', [
                'service' => $service,
                'team_id' => $token->team_id,
                'error' => $e->getMessage()
            ]);

            // Still delete the local token
            $this->repository->revokeToken($token);
            return false;
        }
    }

    /**
     * Get service configuration
     */
    protected function getServiceConfig(string $service): array
    {
        $config = config("auth.oauth.{$service}");
        
        if (!$config) {
            throw new ValidationError("OAuth configuration not found for service: {$service}", 500);
        }

        return $config;
    }

    /**
     * Validate service OAuth configuration
     */
    protected function validateServiceConfiguration(string $service): void
    {
        if (!$this->isConfigured($service)) {
            throw new ValidationError("OAuth is not properly configured for service: {$service}", 500);
        }
    }

    /**
     * Validate token data from OAuth response
     */
    protected function validateTokenData(array $tokenData): void
    {
        if (empty($tokenData['access_token'])) {
            throw new ValidationError('Missing access_token in OAuth response', 400);
        }
    }

    /**
     * Validate that the current team owns the token
     */
    protected function validateTokenOwnership(AuthToken $token): void
    {
        $currentTeam = team();
        if (!$currentTeam || $token->team_id !== $currentTeam->id) {
            throw new ValidationError('You do not have permission to access this OAuth token', 403);
        }
    }
}