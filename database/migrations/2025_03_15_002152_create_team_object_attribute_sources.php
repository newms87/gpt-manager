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
        Schema::create('team_object_attribute_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_object_attribute_id')->constrained()->onDelete('cascade');
            $table->string('source_type');
            $table->string('source_id');
            $table->text('explanation');
            $table->uuid('stored_file_id')->nullable();
            $table->foreignId('agent_thread_message_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('stored_file_id')->references('id')->on('stored_files')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_object_attribute_sources');
    }
};
