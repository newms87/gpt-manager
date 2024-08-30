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
        Schema::create('prompt_schemas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->string('description')->default('');
            $table->string('schema_format');
            $table->json('schema')->nullable();
            $table->json('response_example')->nullable();
            $table->unsignedInteger('agents_count')->default(0);
            $table->unsignedInteger('workflow_jobs_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->foreignId('response_schema_id')->nullable()->after('response_schema')->references('id')->on('prompt_schemas');
        });

        Schema::table('workflow_jobs', function (Blueprint $table) {
            $table->foreignId('response_schema_id')->nullable()->references('id')->on('prompt_schemas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropForeign(['response_schema_id']);
            $table->dropColumn('response_schema_id');
        });
        Schema::table('workflow_jobs', function (Blueprint $table) {
            $table->dropForeign(['response_schema_id']);
            $table->dropColumn('response_schema_id');
        });
        Schema::dropIfExists('prompt_schemas');
    }
};
