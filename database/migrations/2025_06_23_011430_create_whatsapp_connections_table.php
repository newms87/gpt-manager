<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone_number')->default('')->index();
            $table->string('api_provider')->default('twilio');
            $table->string('account_sid')->nullable();
            $table->string('auth_token')->nullable();
            $table->string('access_token')->nullable();
            $table->string('webhook_url')->nullable();
            $table->json('api_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('disconnected');
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'phone_number']);
            $table->index(['team_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_connections');
    }
};
