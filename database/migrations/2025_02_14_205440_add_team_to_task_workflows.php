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
        Schema::table('task_workflows', function (Blueprint $table) {
            if (!Schema::hasColumn('task_workflows', 'name')) {
                $table->string('name')->default('')->after('id');
            }
            if (!Schema::hasColumn('task_workflows', 'team_id')) {
                $table->foreignId('team_id')->after('id')->constrained()->onDelete('cascade');
            }
            $table->string('description')->default('')->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_workflows', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropColumn('team_id');
            $table->dropColumn('description');
        });
    }
};
