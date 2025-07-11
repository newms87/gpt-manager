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
        Schema::table('mcp_servers', function (Blueprint $table) {
            $table->dropColumn(['label', 'require_approval', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mcp_servers', function (Blueprint $table) {
            $table->string('label')->nullable();
            $table->enum('require_approval', ['never', 'always'])->nullable();
            $table->boolean('is_active')->default(true);
        });
    }
};
