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
            $table->string('response_format')->after('prompt')->default('text');
            $table->text('response_notes')->after('response_format')->nullable();
            $table->json('response_schema')->after('response_notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('response_format');
            $table->dropColumn('response_notes');
            $table->dropColumn('response_schema');
        });
    }
};
