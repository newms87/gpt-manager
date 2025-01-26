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
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('response_sub_selection');
            $table->foreignId('response_schema_fragment_id')->nullable()->after('response_schema_id')->constrained('prompt_schema_fragments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->json('response_sub_selection')->nullable()->after('response_schema_id');
            $table->dropForeign(['response_schema_fragment_id']);
            $table->dropColumn('response_schema_fragment_id');
        });
    }
};
