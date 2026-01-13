<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_definition_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_definition_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('html_content');
            $table->text('css_content')->nullable();
            $table->timestamps();

            $table->index('template_definition_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_definition_history');
    }
};
