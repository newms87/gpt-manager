<?php

use App\Models\Schema\SchemaDefinition;
use App\Models\Workflow\WorkflowJob;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach(WorkflowJob::whereNotNull('response_schema')->get() as $workflowJob) {
            $responseSchema = $workflowJob->responseSchema()->updateOrCreate([
                'team_id' => $workflowJob->workflow->team_id,
                'type'    => SchemaDefinition::TYPE_AGENT_RESPONSE,
                'name'    => $workflowJob->name . ' Workflow Job Response Schema',
            ], [
                'schema_format'    => SchemaDefinition::FORMAT_YAML,
                'response_example' => $workflowJob->response_schema,
            ]);

            $workflowJob->update(['response_schema_id' => $responseSchema->id]);
        }
        Schema::table('workflow_jobs', function (Blueprint $table) {
            $table->dropColumn('response_schema');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_jobs', function (Blueprint $table) {
            $table->json('response_schema')->nullable();
        });
    }
};
