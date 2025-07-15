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
        // Change object_id from character(36) to string to allow both UUIDs and integer IDs
        Schema::table('usage_events', function (Blueprint $table) {
            $table->string('object_id')->change();
        });

        Schema::table('usage_summaries', function (Blueprint $table) {
            $table->string('object_id')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usage_events', function (Blueprint $table) {
            $table->char('object_id', 36)->change();
        });

        Schema::table('usage_summaries', function (Blueprint $table) {
            $table->char('object_id', 36)->change();
        });
    }
};
