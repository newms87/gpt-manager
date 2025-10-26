<?php

namespace App\Repositories\Auth;

use App\Models\Auth\AuthToken;
use App\Models\Team\Team;
use Illuminate\Database\Eloquent\Builder;
use Newms87\Danx\Repositories\ActionRepository;

class AuthTokenRepository extends ActionRepository
{
    public static string $model = AuthToken::class;

    /**
     * Apply team-based scoping to all queries
     */
    public function query(): Builder
    {
        return parent::query()->where('team_id', team()->id);
    }

    /**
     * Get OAuth token for a service and team
     */
    public function getOAuthToken(string $service, ?Team $team = null): ?AuthToken
    {
        $team = $team ?: team();

        if (!$team) {
            return null;
        }

        // Don't filter by valid() here - let the service decide what to do with expired tokens
        // The service needs access to soon-to-expire tokens to refresh them
        return AuthToken::forTeam($team->id)
            ->forService($service)
            ->ofType(AuthToken::TYPE_OAUTH)
            ->whereNotNull('access_token')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Get API key for a service and team
     */
    public function getApiKey(string $service, ?string $name = null, ?Team $team = null): ?AuthToken
    {
        $team = $team ?: team();

        if (!$team) {
            return null;
        }

        $query = AuthToken::forTeam($team->id)
            ->forService($service)
            ->ofType(AuthToken::TYPE_API_KEY)
            ->valid();

        if ($name) {
            $query->where('name', $name);
        }

        return $query->first();
    }

    /**
     * Store OAuth token for a team
     */
    public function storeOAuthToken(
        string $service,
        array $tokenData,
        ?Team $team = null,
        array $metadata = []
    ): AuthToken {
        $team = $team ?: team();

        if (!$team) {
            throw new \InvalidArgumentException('Team is required to store OAuth token');
        }

        // Remove existing OAuth token for this service/team (hard delete since we're replacing)
        AuthToken::forTeam($team->id)
            ->forService($service)
            ->ofType(AuthToken::TYPE_OAUTH)
            ->forceDelete();

        $expiresAt = null;
        if (isset($tokenData['expires_in'])) {
            $expiresAt = now()->addSeconds($tokenData['expires_in']);
        }

        $scopes = [];
        if (isset($tokenData['scope'])) {
            $scopes = is_array($tokenData['scope'])
                ? $tokenData['scope']
                : explode(' ', $tokenData['scope']);
        }

        return AuthToken::create([
            'team_id'       => $team->id,
            'service'       => $service,
            'type'          => AuthToken::TYPE_OAUTH,
            'access_token'  => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'id_token'      => $tokenData['id_token']      ?? null,
            'scopes'        => $scopes,
            'expires_at'    => $expiresAt,
            'metadata'      => $metadata,
        ]);
    }

    /**
     * Store API key for a team
     */
    public function storeApiKey(
        string $service,
        string $apiKey,
        ?string $name = null,
        ?Team $team = null,
        array $metadata = []
    ): AuthToken {
        $team = $team ?: team();

        if (!$team) {
            throw new \InvalidArgumentException('Team is required to store API key');
        }

        return AuthToken::create([
            'team_id'      => $team->id,
            'service'      => $service,
            'type'         => AuthToken::TYPE_API_KEY,
            'name'         => $name,
            'access_token' => $apiKey,
            'metadata'     => $metadata,
        ]);
    }

    /**
     * Update OAuth token (typically during refresh)
     */
    public function updateOAuthToken(AuthToken $token, array $tokenData): AuthToken
    {
        $updateData = [
            'access_token' => $tokenData['access_token'],
        ];

        if (isset($tokenData['expires_in'])) {
            $updateData['expires_at'] = now()->addSeconds($tokenData['expires_in']);
        }

        if (isset($tokenData['refresh_token'])) {
            $updateData['refresh_token'] = $tokenData['refresh_token'];
        }

        if (isset($tokenData['scope'])) {
            $updateData['scopes'] = is_array($tokenData['scope'])
                ? $tokenData['scope']
                : explode(' ', $tokenData['scope']);
        }

        $token->update($updateData);

        return $token->fresh();
    }

    /**
     * Revoke/delete token (soft delete for audit trail)
     */
    public function revokeToken(AuthToken $token): bool
    {
        $this->validateTokenOwnership($token);

        return $token->delete();
    }

    /**
     * Hard delete a token (permanent removal)
     */
    public function permanentlyDeleteToken(AuthToken $token): bool
    {
        $this->validateTokenOwnership($token);

        return $token->forceDelete();
    }

    /**
     * Soft delete invalid tokens for cleanup
     */
    public function softDeleteInvalidTokens(string $service, ?Team $team = null): int
    {
        $team = $team ?: team();

        if (!$team) {
            return 0;
        }

        return AuthToken::forTeam($team->id)
            ->forService($service)
            ->ofType(AuthToken::TYPE_OAUTH)
            ->where(function (Builder $query) {
                $query->where('expires_at', '<', now())
                    ->whereNull('refresh_token');
            })
            ->delete();
    }

    /**
     * Get soft deleted tokens for a team (for recovery)
     */
    public function getDeletedTokensForTeam(?Team $team = null): \Illuminate\Database\Eloquent\Collection
    {
        $team = $team ?: team();

        if (!$team) {
            return collect();
        }

        return AuthToken::onlyTrashed()
            ->forTeam($team->id)
            ->orderByDesc('deleted_at')
            ->get();
    }

    /**
     * Restore a soft deleted token
     */
    public function restoreToken(int $tokenId): ?AuthToken
    {
        $token = AuthToken::onlyTrashed()
            ->forTeam(team()->id)
            ->find($tokenId);

        if ($token && $token->restore()) {
            return $token->fresh();
        }

        return null;
    }

    /**
     * Validate that the current team owns the token
     */
    protected function validateTokenOwnership(AuthToken $token): void
    {
        $currentTeam = team();
        if (!$currentTeam || $token->team_id !== $currentTeam->id) {
            throw new \InvalidArgumentException('You do not have permission to access this OAuth token');
        }
    }

    /**
     * Get all tokens for a team
     */
    public function getTokensForTeam(?Team $team = null): \Illuminate\Database\Eloquent\Collection
    {
        $team = $team ?: team();

        if (!$team) {
            return collect();
        }

        return AuthToken::forTeam($team->id)
            ->orderBy('service')
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get tokens expiring soon
     */
    public function getExpiringTokens(int $withinMinutes = 5): \Illuminate\Database\Eloquent\Collection
    {
        $expiresBefore = now()->addMinutes($withinMinutes);

        return AuthToken::ofType(AuthToken::TYPE_OAUTH)
            ->where('expires_at', '<=', $expiresBefore)
            ->where('expires_at', '>', now())
            ->whereNotNull('refresh_token')
            ->get();
    }

    /**
     * Clean up expired tokens without refresh tokens (soft delete)
     */
    public function cleanupExpiredTokens(): int
    {
        return AuthToken::ofType(AuthToken::TYPE_OAUTH)
            ->where('expires_at', '<', now())
            ->whereNull('refresh_token')
            ->delete();
    }

    /**
     * Permanently clean up very old soft deleted tokens for current team
     */
    public function permanentlyCleanupOldDeletedTokens(int $daysOld = 90, ?Team $team = null): int
    {
        $team       = $team ?: team();
        $cutoffDate = now()->subDays($daysOld);

        $query = AuthToken::onlyTrashed()
            ->where('deleted_at', '<', $cutoffDate);

        if ($team) {
            $query->where('team_id', $team->id);
        }

        return $query->forceDelete();
    }
}
