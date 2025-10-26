<?php

namespace Database\Factories\Auth;

use App\Models\Auth\AuthToken;
use App\Models\Team\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuthTokenFactory extends Factory
{
    protected $model = AuthToken::class;

    public function definition(): array
    {
        return [
            'team_id'       => Team::factory(),
            'service'       => AuthToken::SERVICE_GOOGLE,
            'type'          => AuthToken::TYPE_OAUTH,
            'name'          => null,
            'access_token'  => $this->faker->sha256(),
            'refresh_token' => $this->faker->sha256(),
            'id_token'      => $this->faker->sha256(),
            'scopes'        => [
                'https://www.googleapis.com/auth/documents',
                'https://www.googleapis.com/auth/drive',
                'openid',
                'email',
                'profile',
            ],
            'expires_at' => now()->addHour(),
            'metadata'   => [],
        ];
    }

    public function forService(string $service): static
    {
        return $this->state(fn(array $attributes) => [
            'service' => $service,
        ]);
    }

    public function google(): static
    {
        return $this->forService(AuthToken::SERVICE_GOOGLE);
    }

    public function stripe(): static
    {
        return $this->forService(AuthToken::SERVICE_STRIPE);
    }

    public function openai(): static
    {
        return $this->forService(AuthToken::SERVICE_OPENAI);
    }

    public function oauth(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => AuthToken::TYPE_OAUTH,
        ]);
    }

    public function apiKey(): static
    {
        return $this->state(fn(array $attributes) => [
            'type'          => AuthToken::TYPE_API_KEY,
            'expires_at'    => null, // API keys don't expire
            'refresh_token' => null,
            'id_token'      => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn(array $attributes) => [
            'expires_at' => now()->subHour(),
        ]);
    }

    public function expiresSoon(): static
    {
        return $this->state(fn(array $attributes) => [
            'expires_at' => now()->addMinutes(3),
        ]);
    }

    public function withoutAccessToken(): static
    {
        return $this->state(fn(array $attributes) => [
            'access_token' => '',
        ]);
    }

    public function withoutRefreshToken(): static
    {
        return $this->state(fn(array $attributes) => [
            'refresh_token' => '',
        ]);
    }

    public function forTeam(Team $team): static
    {
        return $this->state(fn(array $attributes) => [
            'team_id' => $team->id,
        ]);
    }

    public function withName(string $name): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => $name,
        ]);
    }

    public function withScopes(array $scopes): static
    {
        return $this->state(fn(array $attributes) => [
            'scopes' => $scopes,
        ]);
    }

    public function withMetadata(array $metadata): static
    {
        return $this->state(fn(array $attributes) => [
            'metadata' => $metadata,
        ]);
    }
}
