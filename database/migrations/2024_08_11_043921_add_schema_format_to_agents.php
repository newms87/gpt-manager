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
        Schema::table('agents', function (Blueprint $table) {
            $table->renameColumn('response_format', 'schema_format');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->string('response_format')->default('text')->after('prompt');
        });

        // Update the response_format column so it is set to text if schema_format === text, otherwise set it to json_object
        DB::table('agents')->where('schema_format', 'text')->update(['response_format' => 'text']);
        DB::table('agents')->where('schema_format', '!=', 'text')->update(['response_format' => 'json_object']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('response_format');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->renameColumn('schema_format', 'response_format');
        });
    }
};
