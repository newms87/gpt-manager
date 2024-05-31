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
        Schema::create('content_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('url', 2048);
            $table->json('config')->nullable();
            $table->unsignedInteger('per_page')->default(1000);
            $table->unsignedInteger('polling_interval')->default(60)->comment('in minutes');
            $table->timestamp('polled_at');
            $table->unsignedInteger('workflow_inputs_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'name']);
        });

        Schema::table('workflow_inputs', function (Blueprint $table) {
            $table->foreignId('content_source_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->boolean('is_url')->default(0)->after('is_transcoded');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_inputs', function (Blueprint $table) {
            $table->dropForeign(['content_source_id']);
            $table->dropColumn('content_source_id');
            $table->dropColumn('is_url');
        });

        Schema::dropIfExists('content_sources');
    }
};
