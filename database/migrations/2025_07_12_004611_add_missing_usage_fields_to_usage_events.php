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
        Schema::table('usage_events', function (Blueprint $table) {
            if (!Schema::hasColumn('usage_events', 'api_name')) {
                $table->string('api_name')->nullable()->after('event_type');
            }
            if (!Schema::hasColumn('usage_events', 'request_count')) {
                $table->bigInteger('request_count')->default(0)->after('output_cost');
            }
            if (!Schema::hasColumn('usage_events', 'data_volume')) {
                $table->bigInteger('data_volume')->default(0)->after('request_count');
            }
            if (!Schema::hasColumn('usage_events', 'metadata')) {
                $table->json('metadata')->nullable()->after('data_volume');
            }
        });

        Schema::table('usage_summaries', function (Blueprint $table) {
            if (!Schema::hasColumn('usage_summaries', 'request_count')) {
                $table->bigInteger('request_count')->default(0)->after('total_cost');
            }
            if (!Schema::hasColumn('usage_summaries', 'data_volume')) {
                $table->bigInteger('data_volume')->default(0)->after('request_count');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usage_events', function (Blueprint $table) {
            $table->dropColumn(['api_name', 'request_count', 'data_volume', 'metadata']);
        });

        Schema::table('usage_summaries', function (Blueprint $table) {
            $table->dropColumn(['request_count', 'data_volume']);
        });
    }
};
