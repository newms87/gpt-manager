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
        Schema::create('template_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demand_template_id')->constrained('demand_templates')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();

            // Mapping configuration
            $table->enum('mapping_type', ['ai', 'artifact', 'team_object']);

            // Artifact mapping config
            $table->json('artifact_categories')->nullable();
            $table->json('artifact_fragment_selector')->nullable();

            // Team object mapping config
            $table->foreignId('team_object_schema_association_id')
                ->nullable()
                ->constrained('schema_associations')
                ->nullOnDelete();

            // AI mapping config
            $table->text('ai_instructions')->nullable();

            // Multi-value handling
            $table->enum('multi_value_strategy', ['join', 'first', 'unique'])->default('join');
            $table->string('multi_value_separator')->default(', ');

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('demand_template_id');
            $table->index('deleted_at');
            $table->index('name');

            // Unique constraint: name must be unique per demand_template_id
            $table->unique(['demand_template_id', 'name'], 'template_variables_demand_template_id_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_variables');
    }
};
