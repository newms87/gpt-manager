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
        Schema::table('thread_runs', function (Blueprint $table) {
            $table->decimal('temperature', 5, 2)->nullable()->after('status');
            $table->json('tools')->nullable()->after('temperature');
            $table->string('tool_choice')->default('auto')->after('tools');
            $table->string('response_format')->default('text')->after('tool_choice');
            $table->string('seed')->nullable()->after('response_format');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->json('data')->nullable()->after('content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('thread_runs', function (Blueprint $table) {
            $table->dropColumn('temperature');
            $table->dropColumn('tools');
            $table->dropColumn('tool_choice');
            $table->dropColumn('response_format');
            $table->dropColumn('seed');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('data');
        });
    }
};
