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
        Schema::create('prompt_directives', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('directive_text')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('agent_prompt_directives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prompt_directive_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('section')->default('top');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_prompt_directives');
        Schema::dropIfExists('prompt_directives');
    }
};
