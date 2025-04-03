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
        Schema::create('task_definition_directives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_definition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prompt_directive_id')->constrained()->cascadeOnDelete();
            $table->uuid('resource_package_import')->index()->nullable();
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
        Schema::dropIfExists('task_definition_directives');
    }
};
