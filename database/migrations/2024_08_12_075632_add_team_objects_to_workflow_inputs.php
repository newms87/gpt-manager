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
        Schema::table('workflow_inputs', function (Blueprint $table) {
            $table->unsignedBigInteger('team_object_id')->nullable()->after('is_url');
            $table->string('team_object_type')->nullable()->after('team_object_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_inputs', function (Blueprint $table) {
            $table->dropColumn('team_object_id');
            $table->dropColumn('team_object_type');
        });
    }
};
