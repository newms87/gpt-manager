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
        Schema::table('object_attributes', function (Blueprint $table) {
            $table->string('confidence')->nullable()->after('json_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('object_attributes', function (Blueprint $table) {
            $table->dropColumn('confidence');
        });
    }
};
