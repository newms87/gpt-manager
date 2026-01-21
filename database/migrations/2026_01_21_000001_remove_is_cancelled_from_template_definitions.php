<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('template_definitions', 'is_cancelled')) {
            Schema::table('template_definitions', function (Blueprint $table) {
                $table->dropColumn('is_cancelled');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('template_definitions', 'is_cancelled')) {
            Schema::table('template_definitions', function (Blueprint $table) {
                $table->boolean('is_cancelled')->default(false)->after('pending_build_context');
            });
        }
    }
};
