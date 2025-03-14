<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('workflow_tasks');
        Schema::dropIfExists('workflow_assignments');
        Schema::dropIfExists('workflow_job_dependencies');
        Schema::dropIfExists('workflow_job_runs');
        Schema::dropIfExists('workflow_jobs');
        Schema::dropIfExists('workflow_runs');
        Schema::dropIfExists('workflows');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
