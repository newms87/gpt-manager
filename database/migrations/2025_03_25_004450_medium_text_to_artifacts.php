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
        Schema::table('artifacts', function (Blueprint $table) {
            $table->mediumText('text_content')->nullable()->change();
        });

        Schema::table('workflow_inputs', function (Blueprint $table) {
            $table->mediumText('content')->nullable()->change();
        });

        Schema::table('prompt_directives', function (Blueprint $table) {
            $table->mediumText('directive_text')->nullable()->change();
        });

        Schema::table('audit_request', function (Blueprint $table) {
            $table->mediumText('logs')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
