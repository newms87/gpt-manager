<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agents', function ($table) {
            $table->dropColumn('prompt');
            $table->dropColumn('response_notes');
            $table->dropColumn('response_schema');
            $table->dropColumn('schema_format');
            $table->dropColumn('response_sample');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function ($table) {
            $table->string('prompt')->nullable();
            $table->string('schema_format')->nullable();
            $table->string('response_notes')->nullable();
            $table->json('response_schema')->nullable();
            $table->json('response_sample')->nullable();
        });
    }
};
