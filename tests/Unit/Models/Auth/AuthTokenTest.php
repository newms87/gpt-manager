<?php

namespace Tests\Unit\Models\Auth;

use App\Models\Auth\AuthToken;
use App\Models\Team\Team;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class AuthTokenTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }





    public function test_isExpired_withExpiredOAuthToken_returnsTrue(): void
    {
        // Given
        $token = AuthToken::factory()->oauth()->create([
            'expires_at' => now()->subHour()
        ]);

        // When & Then
        $this->assertTrue($token->isExpired());
    }

    public function test_isExpired_withValidOAuthToken_returnsFalse(): void
    {
        // Given
        $token = AuthToken::factory()->oauth()->create([
            'expires_at' => now()->addHour()
        ]);

        // When & Then
        $this->assertFalse($token->isExpired());
    }

    public function test_isExpired_withApiKeyToken_returnsFalse(): void
    {
        // Given
        $token = AuthToken::factory()->apiKey()->create();

        // When & Then
        $this->assertFalse($token->isExpired());
    }

    public function test_isValid_withValidOAuthToken_returnsTrue(): void
    {
        // Given
        $token = AuthToken::factory()->oauth()->create([
            'access_token' => 'valid_access_token',
            'expires_at' => now()->addHour()
        ]);

        // When & Then
        $this->assertTrue($token->isValid());
    }

    public function test_isValid_withValidApiKeyToken_returnsTrue(): void
    {
        // Given
        $token = AuthToken::factory()->apiKey()->create([
            'access_token' => 'valid_api_key'
        ]);

        // When & Then
        $this->assertTrue($token->isValid());
    }

    public function test_isValid_withExpiredOAuthToken_returnsFalse(): void
    {
        // Given
        $token = AuthToken::factory()->oauth()->create([
            'expires_at' => now()->subHour()
        ]);

        // When & Then
        $this->assertFalse($token->isValid());
    }

    public function test_isValid_withMissingAccessToken_returnsFalse(): void
    {
        // Given
        $token = AuthToken::factory()->oauth()->create([
            'access_token' => '',
            'expires_at' => now()->addHour()
        ]);

        // When & Then
        $this->assertFalse($token->isValid());
    }

    public function test_willExpireWithin_withSoonToExpireToken_returnsTrue(): void
    {
        // Given
        $token = AuthToken::factory()->oauth()->create([
            'expires_at' => now()->addMinutes(3)
        ]);

        // When & Then
        $this->assertTrue($token->willExpireWithin(5));
    }

    public function test_willExpireWithin_withFarFutureToken_returnsFalse(): void
    {
        // Given
        $token = AuthToken::factory()->oauth()->create([
            'expires_at' => now()->addMinutes(10)
        ]);

        // When & Then
        $this->assertFalse($token->willExpireWithin(5));
    }

    public function test_willExpireWithin_withApiKeyToken_returnsFalse(): void
    {
        // Given
        $token = AuthToken::factory()->apiKey()->create();

        // When & Then
        $this->assertFalse($token->willExpireWithin(5));
    }

    public function test_hasScope_withValidScope_returnsTrue(): void
    {
        // Given
        $scopes = ['scope1', 'scope2', 'scope3'];
        $token = AuthToken::factory()->create(['scopes' => $scopes]);

        // When & Then
        $this->assertTrue($token->hasScope('scope2'));
    }

    public function test_hasScope_withInvalidScope_returnsFalse(): void
    {
        // Given
        $scopes = ['scope1', 'scope2', 'scope3'];
        $token = AuthToken::factory()->create(['scopes' => $scopes]);

        // When & Then
        $this->assertFalse($token->hasScope('nonexistent_scope'));
    }

    public function test_hasScopes_withAllValidScopes_returnsTrue(): void
    {
        // Given
        $scopes = ['scope1', 'scope2', 'scope3'];
        $token = AuthToken::factory()->create(['scopes' => $scopes]);

        // When & Then
        $this->assertTrue($token->hasScopes(['scope1', 'scope3']));
    }

    public function test_hasScopes_withSomeInvalidScopes_returnsFalse(): void
    {
        // Given
        $scopes = ['scope1', 'scope2', 'scope3'];
        $token = AuthToken::factory()->create(['scopes' => $scopes]);

        // When & Then
        $this->assertFalse($token->hasScopes(['scope1', 'nonexistent_scope']));
    }

    public function test_getDisplayName_withCustomName_returnsCustomName(): void
    {
        // Given
        $token = AuthToken::factory()->create(['name' => 'My Custom Token']);

        // When & Then
        $this->assertEquals('My Custom Token', $token->getDisplayName());
    }

    public function test_getDisplayName_withoutCustomName_returnsServiceAndType(): void
    {
        // Given
        $token = AuthToken::factory()->google()->oauth()->create(['name' => null]);

        // When & Then
        $this->assertEquals('Google Oauth', $token->getDisplayName());
    }

    public function test_getMetadata_withExistingKey_returnsValue(): void
    {
        // Given
        $metadata = ['key1' => 'value1', 'key2' => 'value2'];
        $token = AuthToken::factory()->create(['metadata' => $metadata]);

        // When & Then
        $this->assertEquals('value1', $token->getMetadata('key1'));
    }

    public function test_getMetadata_withNonExistentKey_returnsDefault(): void
    {
        // Given
        $metadata = ['key1' => 'value1'];
        $token = AuthToken::factory()->create(['metadata' => $metadata]);

        // When & Then
        $this->assertEquals('default_value', $token->getMetadata('nonexistent_key', 'default_value'));
        $this->assertNull($token->getMetadata('nonexistent_key'));
    }

    public function test_setMetadata_updatesMetadataArray(): void
    {
        // Given
        $token = AuthToken::factory()->create(['metadata' => ['existing_key' => 'existing_value']]);

        // When
        $token->setMetadata('new_key', 'new_value');

        // Then
        $this->assertEquals('new_value', $token->getMetadata('new_key'));
        $this->assertEquals('existing_value', $token->getMetadata('existing_key'));
    }

    public function test_scopeForService_filtersToSpecificService(): void
    {
        // Given
        AuthToken::factory()->google()->forTeam($this->user->currentTeam)->create();
        AuthToken::factory()->stripe()->forTeam($this->user->currentTeam)->create();

        // When
        $googleTokens = AuthToken::forService('google')->get();
        $stripeTokens = AuthToken::forService('stripe')->get();

        // Then
        $this->assertEquals(1, $googleTokens->count());
        $this->assertEquals('google', $googleTokens->first()->service);
        
        $this->assertEquals(1, $stripeTokens->count());
        $this->assertEquals('stripe', $stripeTokens->first()->service);
    }

    public function test_scopeOfType_filtersToSpecificType(): void
    {
        // Given
        AuthToken::factory()->oauth()->forTeam($this->user->currentTeam)->create();
        AuthToken::factory()->apiKey()->forTeam($this->user->currentTeam)->create();

        // When
        $oauthTokens = AuthToken::ofType(AuthToken::TYPE_OAUTH)->get();
        $apiKeyTokens = AuthToken::ofType(AuthToken::TYPE_API_KEY)->get();

        // Then
        $this->assertEquals(1, $oauthTokens->count());
        $this->assertEquals(AuthToken::TYPE_OAUTH, $oauthTokens->first()->type);
        
        $this->assertEquals(1, $apiKeyTokens->count());
        $this->assertEquals(AuthToken::TYPE_API_KEY, $apiKeyTokens->first()->type);
    }

    public function test_scopeForTeam_filtersToSpecificTeam(): void
    {
        // Given
        $otherTeam = Team::factory()->create();
        AuthToken::factory()->forTeam($this->user->currentTeam)->create();
        AuthToken::factory()->forTeam($otherTeam)->create();

        // When
        $teamTokens = AuthToken::forTeam($this->user->currentTeam->id)->get();
        $otherTeamTokens = AuthToken::forTeam($otherTeam->id)->get();

        // Then
        $this->assertEquals(1, $teamTokens->count());
        $this->assertEquals($this->user->currentTeam->id, $teamTokens->first()->team_id);
        
        $this->assertEquals(1, $otherTeamTokens->count());
        $this->assertEquals($otherTeam->id, $otherTeamTokens->first()->team_id);
    }

    public function test_scopeValid_filtersToValidTokensOnly(): void
    {
        // Given
        $validOAuth = AuthToken::factory()->oauth()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'valid_token',
            'expires_at' => now()->addHour()
        ]);
        
        $expiredOAuth = AuthToken::factory()->oauth()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'expired_token',
            'expires_at' => now()->subHour()
        ]);
        
        $validApiKey = AuthToken::factory()->apiKey()->forTeam($this->user->currentTeam)->create([
            'access_token' => 'valid_api_key'
        ]);
        
        $emptyAccessToken = AuthToken::factory()->oauth()->forTeam($this->user->currentTeam)->create([
            'access_token' => '',
            'expires_at' => now()->addHour()
        ]);

        // When
        $validTokens = AuthToken::valid()->get();

        // Then
        $this->assertEquals(2, $validTokens->count());
        $this->assertTrue($validTokens->contains($validOAuth));
        $this->assertTrue($validTokens->contains($validApiKey));
        $this->assertFalse($validTokens->contains($expiredOAuth));
        $this->assertFalse($validTokens->contains($emptyAccessToken));
    }

}