<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stored_files', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->after('original_stored_file_id');
            $table->unsignedBigInteger('user_id')->nullable()->after('team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stored_files', function (Blueprint $table) {
            $table->dropColumn(['team_id', 'user_id']);
        });
    }
};
