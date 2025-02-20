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
        if (!Schema::hasColumn('stored_files', 'page_number')) {
            Schema::table('stored_files', function (Blueprint $table) {
                $table->unsignedInteger('page_number')->nullable()->after('location');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stored_files', function (Blueprint $table) {
            //
        });
    }
};
