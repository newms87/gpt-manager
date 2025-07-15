<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('usage_events', function (Blueprint $table) {
            $table->bigInteger('object_id_int')->nullable()->after('object_id');
            $table->index(['object_type', 'object_id_int']);
        });

        Schema::table('usage_summaries', function (Blueprint $table) {
            $table->bigInteger('object_id_int')->nullable()->after('object_id');
            $table->index(['object_type', 'object_id_int']);
        });

        // Populate the new integer fields with existing data
        DB::statement("UPDATE usage_events SET object_id_int = CASE WHEN object_id ~ '^[0-9]+$' THEN object_id::bigint ELSE NULL END");
        DB::statement("UPDATE usage_summaries SET object_id_int = CASE WHEN object_id ~ '^[0-9]+$' THEN object_id::bigint ELSE NULL END");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usage_events', function (Blueprint $table) {
            $table->dropIndex(['object_type', 'object_id_int']);
            $table->dropColumn('object_id_int');
        });

        Schema::table('usage_summaries', function (Blueprint $table) {
            $table->dropIndex(['object_type', 'object_id_int']);
            $table->dropColumn('object_id_int');
        });
    }
};
