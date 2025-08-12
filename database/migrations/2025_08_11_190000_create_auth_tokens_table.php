<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('auth_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->string('service'); // google, stripe, openai, etc.
            $table->string('type'); // oauth, api_key
            $table->string('name')->nullable(); // user-defined name for API keys
            $table->text('access_token')->nullable(); // OAuth access token or API key value
            $table->text('refresh_token')->nullable(); // OAuth refresh token
            $table->text('id_token')->nullable(); // OAuth ID token (optional)
            $table->json('scopes')->nullable(); // OAuth scopes
            $table->timestamp('expires_at')->nullable(); // expiration for OAuth tokens
            $table->json('metadata')->nullable(); // service-specific data
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['team_id', 'service', 'type']);
            $table->index(['service', 'type']);
            $table->index('expires_at');
            
            // Unique constraint: one OAuth token per service per team
            $table->unique(['team_id', 'service', 'type', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth_tokens');
    }
};
