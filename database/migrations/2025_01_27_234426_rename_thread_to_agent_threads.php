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
        Schema::rename('threads', 'agent_threads');
        Schema::rename('thread_runs', 'agent_thread_runs');
        Schema::rename('messages', 'agent_thread_messages');
        Schema::rename('messageables', 'agent_thread_messageables');


        Schema::table('agent_thread_messages', function (Blueprint $table) {
            $table->renameColumn('thread_id', 'agent_thread_id');
        });

        Schema::table('agent_thread_runs', function (Blueprint $table) {
            $table->renameColumn('thread_id', 'agent_thread_id');
        });

        Schema::table('agent_thread_messageables', function (Blueprint $table) {
            $table->renameColumn('message_id', 'agent_thread_message_id');
        });

        Schema::table('task_processes', function (Blueprint $table) {
            $table->renameColumn('thread_id', 'agent_thread_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //        Schema::rename('agent_threads', 'threads');
        //        Schema::rename('agent_thread_runs', 'thread_runs');
        //        Schema::rename('agent_thread_messages', 'messages');
        //        Schema::rename('agent_thread_messageables', 'messageables');

        Schema::table('messages', function (Blueprint $table) {
            $table->renameColumn('agent_thread_id', 'thread_id');
        });

        Schema::table('thread_runs', function (Blueprint $table) {
            $table->renameColumn('agent_thread_id', 'thread_id');
        });

        Schema::table('messageables', function (Blueprint $table) {
            $table->renameColumn('agent_thread_message_id', 'message_id');
        });

        Schema::table('task_processes', function (Blueprint $table) {
            $table->renameColumn('agent_thread_id', 'thread_id');
        });
    }
};
