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
            $table->foreignId('job_dispatch_id')->nullable()->after('last_message_id')->constrained('job_dispatch');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('thread_runs', function (Blueprint $table) {
            $table->dropForeign(['job_dispatch_id']);
            $table->dropColumn('job_dispatch_id');
        });
    }
};
