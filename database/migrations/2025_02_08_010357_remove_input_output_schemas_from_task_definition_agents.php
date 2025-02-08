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
        Schema::table('task_definition_agents', function (Blueprint $table) {
            $table->dropForeign(['input_schema_id']);
            $table->dropForeign(['input_schema_fragment_id']);
            $table->dropForeign(['output_schema_id']);
            $table->dropForeign(['output_schema_fragment_id']);
            $table->removeColumn('input_schema_id');
            $table->removeColumn('input_schema_fragment_id');
            $table->removeColumn('output_schema_id');
            $table->removeColumn('output_schema_fragment_id');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_definition_agents', function (Blueprint $table) {
            $table->unsignedBigInteger('input_schema_id')->nullable();
            $table->unsignedBigInteger('input_schema_fragment_id')->nullable();
            $table->unsignedBigInteger('output_schema_id')->nullable();
            $table->unsignedBigInteger('output_schema_fragment_id')->nullable();
        });
    }
};
