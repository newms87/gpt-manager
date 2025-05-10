<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('task_process_listeners', function (Blueprint $table) {
            $table->dropColumn('event_id');
        });
        Schema::table('task_process_listeners', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
