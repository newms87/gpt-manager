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
        Schema::create('task_process_listeners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_process_id')->constrained();
            $table->string('event_type');
            $table->string('event_id');
            $table->datetime('event_fired_at');
            $table->datetime('event_handled_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_process_listeners');
    }
};
