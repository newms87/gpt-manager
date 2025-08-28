<?php

namespace Tests\Unit\Repositories\Auth;

use App\Models\Auth\AuthToken;
use App\Models\Team\Team;
use App\Repositories\Auth\AuthTokenRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class AuthTokenRepositoryTest extends AuthenticatedTestCase
{
    use RefreshDatabase, SetUpTeamTrait;

    protected AuthTokenRepository $repository;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        
        $this->repository = app(AuthTokenRepository::class);
    }

    public function test_query_appliesTeamScoping(): void
    {
        // Given
        $otherTeam = Team::factory()->create();
        
        // Create tokens for different teams
        $ourToken = AuthToken::factory()->google()->create(['team_id' => $this->user->currentTeam->id]);
        $otherToken = AuthToken::factory()->google()->create(['team_id' => $otherTeam->id]);

        // When
        $results = $this->repository->query()->get();

        // Then
        $this->assertCount(1, $results);
        $this->assertEquals($ourToken->id, $results->first()->id);
        $this->assertNotContains($otherToken->id, $results->pluck('id'));
    }

    public function test_getOAuthToken_withValidTeam_returnsToken(): void
    {
        // Given
        $token = AuthToken::factory()->google()->create(['team_id' => $this->user->currentTeam->id]);

        // When
        $result = $this->repository->getOAuthToken('google', $this->user->currentTeam);

        // Then
        $this->assertInstanceOf(AuthToken::class, $result);
        $this->assertEquals($token->id, $result->id);
    }

    public function test_getOAuthToken_withoutTeam_usesCurrentTeam(): void
    {
        // Given
        $token = AuthToken::factory()->google()->create(['team_id' => $this->user->currentTeam->id]);

        // When
        $result = $this->repository->getOAuthToken('google');

        // Then
        $this->assertInstanceOf(AuthToken::class, $result);
        $this->assertEquals($token->id, $result->id);
    }

    public function test_getOAuthToken_withDifferentTeam_returnsNull(): void
    {
        // Given
        $otherTeam = Team::factory()->create();
        AuthToken::factory()->google()->create(['team_id' => $otherTeam->id]);

        // When
        $result = $this->repository->getOAuthToken('google', $this->user->currentTeam);

        // Then
        $this->assertNull($result);
    }

    public function test_storeOAuthToken_replacesExistingToken(): void
    {
        // Given
        $existingToken = AuthToken::factory()->google()->create(['team_id' => $this->user->currentTeam->id]);
        $tokenData = [
            'access_token' => 'new_access_token',
            'refresh_token' => 'new_refresh_token',
            'expires_in' => 3600,
            'scope' => 'https://www.googleapis.com/auth/documents'
        ];

        // When
        $newToken = $this->repository->storeOAuthToken('google', $tokenData, $this->user->currentTeam);

        // Then
        $this->assertInstanceOf(AuthToken::class, $newToken);
        $this->assertEquals('new_access_token', $newToken->access_token);
        
        // Old token should be deleted
        $this->assertDatabaseMissing('auth_tokens', [
            'id' => $existingToken->id
        ]);
    }

    public function test_storeOAuthToken_withScopesArray_storesCorrectly(): void
    {
        // Given
        $tokenData = [
            'access_token' => 'test_token',
            'scope' => ['scope1', 'scope2']
        ];

        // When
        $token = $this->repository->storeOAuthToken('google', $tokenData, $this->user->currentTeam);

        // Then
        $this->assertEquals(['scope1', 'scope2'], $token->scopes);
    }

    public function test_storeOAuthToken_withScopesString_convertsToArray(): void
    {
        // Given
        $tokenData = [
            'access_token' => 'test_token',
            'scope' => 'scope1 scope2 scope3'
        ];

        // When
        $token = $this->repository->storeOAuthToken('google', $tokenData, $this->user->currentTeam);

        // Then
        $this->assertEquals(['scope1', 'scope2', 'scope3'], $token->scopes);
    }

    public function test_revokeToken_softDeletesToken(): void
    {
        // Given
        $token = AuthToken::factory()->google()->create(['team_id' => $this->user->currentTeam->id]);

        // When
        $result = $this->repository->revokeToken($token);

        // Then
        $this->assertTrue($result);
        $this->assertTrue($token->fresh()->trashed());
    }

    public function test_revokeToken_withWrongTeam_throwsException(): void
    {
        // Given
        $otherTeam = Team::factory()->create();
        $token = AuthToken::factory()->google()->create(['team_id' => $otherTeam->id]);

        // When & Then
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('You do not have permission to access this OAuth token');
        
        $this->repository->revokeToken($token);
    }

    public function test_permanentlyDeleteToken_hardDeletesToken(): void
    {
        // Given
        $token = AuthToken::factory()->google()->create(['team_id' => $this->user->currentTeam->id]);
        $tokenId = $token->id;

        // When
        $result = $this->repository->permanentlyDeleteToken($token);

        // Then
        $this->assertTrue($result);
        $this->assertDatabaseMissing('auth_tokens', [
            'id' => $tokenId
        ]);
    }

    public function test_softDeleteInvalidTokens_deletesExpiredTokensWithoutRefresh(): void
    {
        // Given
        $validToken = AuthToken::factory()->google()->create([
            'team_id' => $this->user->currentTeam->id,
            'expires_at' => now()->addHour()
        ]);
        
        $expiredWithRefresh = AuthToken::factory()->google()->create([
            'team_id' => $this->user->currentTeam->id,
            'expires_at' => now()->subHour(),
            'refresh_token' => 'valid_refresh_token'
        ]);
        
        $expiredWithoutRefresh = AuthToken::factory()->google()->create([
            'team_id' => $this->user->currentTeam->id,
            'expires_at' => now()->subHour(),
            'refresh_token' => null
        ]);

        // When
        $deletedCount = $this->repository->softDeleteInvalidTokens('google', $this->user->currentTeam);

        // Then
        $this->assertEquals(1, $deletedCount);
        $this->assertFalse($validToken->fresh()->trashed());
        $this->assertFalse($expiredWithRefresh->fresh()->trashed());
        $this->assertTrue($expiredWithoutRefresh->fresh()->trashed());
    }

    public function test_getDeletedTokensForTeam_returnsOnlySoftDeletedTokens(): void
    {
        // Given
        $activeToken = AuthToken::factory()->google()->create(['team_id' => $this->user->currentTeam->id]);
        $deletedToken = AuthToken::factory()->google()->create(['team_id' => $this->user->currentTeam->id]);
        $deletedToken->delete();

        // When
        $deletedTokens = $this->repository->getDeletedTokensForTeam($this->user->currentTeam);

        // Then
        $this->assertCount(1, $deletedTokens);
        $this->assertEquals($deletedToken->id, $deletedTokens->first()->id);
        $this->assertNotContains($activeToken->id, $deletedTokens->pluck('id'));
    }

    public function test_restoreToken_restoresSoftDeletedToken(): void
    {
        // Given
        $token = AuthToken::factory()->google()->create(['team_id' => $this->user->currentTeam->id]);
        $token->delete();
        $this->assertTrue($token->fresh()->trashed());

        // When
        $restoredToken = $this->repository->restoreToken($token->id);

        // Then
        $this->assertInstanceOf(AuthToken::class, $restoredToken);
        $this->assertEquals($token->id, $restoredToken->id);
        $this->assertFalse($restoredToken->trashed());
    }

    public function test_restoreToken_withNonexistentToken_returnsNull(): void
    {
        // When
        $result = $this->repository->restoreToken(999);

        // Then
        $this->assertNull($result);
    }

    public function test_getExpiringTokens_returnsTokensExpiringSoon(): void
    {
        // Given
        $expiringSoon = AuthToken::factory()->google()->create([
            'team_id' => $this->user->currentTeam->id,
            'expires_at' => now()->addMinutes(3),
            'refresh_token' => 'valid_refresh'
        ]);
        
        $expiringSoon2 = AuthToken::factory()->google()->create([
            'team_id' => $this->user->currentTeam->id,
            'expires_at' => now()->addMinutes(4),
            'refresh_token' => 'valid_refresh'
        ]);
        
        $notExpiring = AuthToken::factory()->google()->create([
            'team_id' => $this->user->currentTeam->id,
            'expires_at' => now()->addHour(),
            'refresh_token' => 'valid_refresh'
        ]);
        
        $alreadyExpired = AuthToken::factory()->google()->create([
            'team_id' => $this->user->currentTeam->id,
            'expires_at' => now()->subHour(),
            'refresh_token' => 'valid_refresh'
        ]);

        $noRefreshToken = AuthToken::factory()->google()->create([
            'team_id' => $this->user->currentTeam->id,
            'expires_at' => now()->addMinutes(2),
            'refresh_token' => null
        ]);

        // When
        $expiringTokens = $this->repository->getExpiringTokens(5);

        // Then
        $this->assertCount(2, $expiringTokens);
        $tokenIds = $expiringTokens->pluck('id')->toArray();
        $this->assertContains($expiringSoon->id, $tokenIds);
        $this->assertContains($expiringSoon2->id, $tokenIds);
        $this->assertNotContains($notExpiring->id, $tokenIds);
        $this->assertNotContains($alreadyExpired->id, $tokenIds);
        $this->assertNotContains($noRefreshToken->id, $tokenIds);
    }

    public function test_cleanupExpiredTokens_deletesExpiredTokensWithoutRefresh(): void
    {
        // Given
        $expiredWithRefresh = AuthToken::factory()->google()->create([
            'expires_at' => now()->subHour(),
            'refresh_token' => 'valid_refresh'
        ]);
        
        $expiredWithoutRefresh = AuthToken::factory()->google()->create([
            'expires_at' => now()->subHour(),
            'refresh_token' => null
        ]);
        
        $validToken = AuthToken::factory()->google()->create([
            'expires_at' => now()->addHour(),
            'refresh_token' => null
        ]);

        // When
        $deletedCount = $this->repository->cleanupExpiredTokens();

        // Then
        $this->assertEquals(1, $deletedCount);
        $this->assertFalse($expiredWithRefresh->fresh()->trashed());
        $this->assertTrue($expiredWithoutRefresh->fresh()->trashed());
        $this->assertFalse($validToken->fresh()->trashed());
    }

    public function test_permanentlyCleanupOldDeletedTokens_removesOldSoftDeletedTokens(): void
    {
        // Given
        $recentlyDeleted = AuthToken::factory()->google()->create(['team_id' => $this->user->currentTeam->id]);
        $recentlyDeleted->delete();
        
        $oldDeleted = AuthToken::factory()->google()->create(['team_id' => $this->user->currentTeam->id]);
        $oldDeleted->delete();
        // Manually set deleted_at to be very old - need to update the trashed record
        $oldDeleted->withTrashed()->where('id', $oldDeleted->id)->update(['deleted_at' => now()->subDays(100)]);

        // When
        $deletedCount = $this->repository->permanentlyCleanupOldDeletedTokens(90, $this->user->currentTeam);

        // Then
        $this->assertEquals(1, $deletedCount);
        $this->assertDatabaseHas('auth_tokens', ['id' => $recentlyDeleted->id]);
        $this->assertDatabaseMissing('auth_tokens', ['id' => $oldDeleted->id]);
    }
}