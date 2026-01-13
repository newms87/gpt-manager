<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_definitions', function (Blueprint $table) {
            $table->string('type')->default('google_docs')->after('team_id');
            $table->longText('html_content')->nullable()->after('metadata');
            $table->text('css_content')->nullable()->after('html_content');
            $table->char('preview_stored_file_id', 36)->nullable()->after('css_content');

            $table->foreign('preview_stored_file_id')->references('id')->on('stored_files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('template_definitions', function (Blueprint $table) {
            $table->dropForeign(['preview_stored_file_id']);
            $table->dropColumn(['type', 'html_content', 'css_content', 'preview_stored_file_id']);
        });
    }
};
