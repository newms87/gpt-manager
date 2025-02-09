<?php

use App\Models\Agent\Agent;
use App\Models\Prompt\AgentPromptDirective;
use App\Models\Prompt\PromptDirective;
use App\Models\Schema\SchemaDefinition;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $agents = Agent::all();

        foreach($agents as $agent) {
            $agent->responseSchema()->updateOrCreate([
                'team_id' => $agent->team_id,
                'name'    => $agent->name . ' Schema',
            ],
                [
                    'type'          => SchemaDefinition::TYPE_AGENT_RESPONSE,
                    'schema_format' => $agent->schema_format ?: SchemaDefinition::FORMAT_YAML,
                    'schema'        => $agent->response_schema,
                ]);


            $topPromptDirective = PromptDirective::updateOrCreate([
                'team_id' => $agent->team_id,
                'name'    => $agent->name . ' Main Prompt',
            ],
                [
                    'directive_text' => $agent->prompt,
                ]);
            $agent->directives()->create([
                'section'             => AgentPromptDirective::SECTION_TOP,
                'position'            => 0,
                'prompt_directive_id' => $topPromptDirective->id,
            ]);

            $bottomPromptDirective = PromptDirective::updateOrCreate([
                'team_id' => $agent->team_id,
                'name'    => $agent->name . ' After Prompt',
            ],
                [
                    'directive_text' => $agent->response_notes,
                ]);
            $agent->directives()->create([
                'section'             => AgentPromptDirective::SECTION_BOTTOM,
                'position'            => 1,
                'prompt_directive_id' => $bottomPromptDirective->id,
            ]);
        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
