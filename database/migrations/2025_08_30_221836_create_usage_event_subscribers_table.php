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
        Schema::create('usage_event_subscribers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usage_event_id')->constrained('usage_events')->onDelete('cascade');
            $table->string('subscriber_type');
            $table->string('subscriber_id');
            $table->unsignedBigInteger('subscriber_id_int');
            $table->timestamp('subscribed_at')->useCurrent();
            $table->timestamps();

            $table->index(['usage_event_id', 'subscriber_type', 'subscriber_id_int']);
            $table->index(['subscriber_type', 'subscriber_id_int']);
            $table->unique(['usage_event_id', 'subscriber_type', 'subscriber_id_int'], 'usage_event_subscriber_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_event_subscribers');
    }
};
