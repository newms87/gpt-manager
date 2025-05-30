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
        Schema::table('artifacts', function (Blueprint $table) {
            $table->foreignId('task_process_id')->nullable()->after('task_definition_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('artifacts', function (Blueprint $table) {
            $table->dropForeign(['task_process_id']);
            $table->dropColumn('task_process_id');
        });
    }
};
