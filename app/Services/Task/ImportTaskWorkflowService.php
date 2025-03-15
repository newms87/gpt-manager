<?php

namespace App\Services\Task;

use App\Models\Agent\Agent;
use App\Models\Prompt\PromptDirective;
use App\Models\Schema\SchemaDefinition;
use App\Models\Schema\SchemaFragment;
use App\Models\Task\TaskWorkflow;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\ModelHelper;

class ImportTaskWorkflowService
{
    public function exportToJson(TaskWorkflow $taskWorkflow)
    {
        // TODO: When exporting we need to set the owner_team_id fields to all relevant objects
        // importing will need to import that field as well to lock users out of seeing those objects
        $data = [];
    }

    public function importFromJson(string $workflowJson): bool
    {
        $data = json_decode($workflowJson, true);

        $name = $data['name'] ?? null;
        if (!$name) {
            throw new ValidationError('Workflow name is required');
        }

        $existingWorkflow = Workflow::where('name', $name)->where('team_id', team()->id)->first();
        if ($existingWorkflow) {
            $name = ModelHelper::getNextModelName($existingWorkflow);
        }
        $workflow = Workflow::create([
            'name'        => $name,
            'description' => $data['description'],
            'team_id'     => team()->id,
        ]);

        foreach($data['jobs'] as $jobData) {
            $workflowJob = $workflow->workflowJobs()->create(collect($jobData)->except('responseSchema', 'assignments', 'dependencies')->toArray());

            // Fill in Response Schema
            $responseSchemaData = $jobData['responseSchema'] ?? null;

            if ($responseSchemaData) {
                $responseSchema = SchemaDefinition::updateOrCreate([
                    'team_id' => team()->id,
                    'name'    => $responseSchemaData['name'],
                ],
                    collect($responseSchemaData)->except('name')->toArray()
                );
                $workflowJob->responseSchema()->associate($responseSchema)->save();
            }

            // Fill in assignments for workflow job
            foreach($jobData['assignments'] as $assignmentData) {
                // Create the Agent
                $agentData = $assignmentData['agent'];
                $agent     = Agent::updateOrCreate(
                    [
                        'team_id' => team()->id,
                        'name'    => $agentData['name'],
                    ],
                    collect($agentData)->except(['name', 'directives', 'responseSchema'])->toArray()
                );

                // Assign agent to workflow job
                $workflowJob->workflowAssignments()->create([
                    'agent_id'     => $agent->id,
                    'is_required'  => $assignmentData['is_required'],
                    'max_attempts' => $assignmentData['max_attempts'],
                ]);

                // Fill in Prompt Response schema
                $responseSchemaData = $agentData['responseSchema'] ?? null;

                if ($responseSchemaData) {
                    $responseSchema = SchemaDefinition::updateOrCreate([
                        'team_id' => team()->id,
                        'name'    => $responseSchemaData['name'],
                    ],
                        collect($responseSchemaData)->except('name')->toArray()
                    );

                    // Fill in Response Schema Fragment
                    $responseSchemaFragmentData = $jobData['responseSchemaFragment'] ?? null;

                    if ($responseSchemaFragmentData) {
                        $responseSchemaFragment = SchemaFragment::updateOrCreate([
                            'schema_definition_id' => $responseSchema->id,
                            'name'                 => $responseSchemaFragmentData['name'],
                        ],
                            collect($responseSchemaFragmentData)->except('name')->toArray()
                        );
                    }
                }

                // Create Directives
                foreach($agentData['directives'] as $agentDirectiveData) {
                    $directiveData = $agentDirectiveData['directive'];
                    $directive     = PromptDirective::updateOrCreate(
                        [
                            'team_id' => team()->id,
                            'name'    => $directiveData['name'],
                        ],
                        [
                            'directive_text' => $directiveData['directive_text'],
                        ]);
                    $agent->directives()->create([
                        'section'             => $agentDirectiveData['section'],
                        'position'            => $agentDirectiveData['position'],
                        'prompt_directive_id' => $directive->id,
                    ]);
                }
            }
        }

        // Create dependencies after all jobs and assignments have been created so all required jobs exist
        foreach($data['jobs'] as $jobData) {
            $workflowJob = $workflow->workflowJobs()->where('name', $jobData['name'])->first();
            foreach($jobData['dependencies'] as $dependencyData) {
                $dependsOnJob = $workflow->workflowJobs()->where('name', $dependencyData['depends_on_workflow_job_name'])->first();
                $workflowJob->dependencies()->create([
                    'depends_on_workflow_job_id' => $dependsOnJob->id,
                    ...collect($dependencyData)->except('depends_on_workflow_job_name')->toArray(),
                ]);
            }
        }

        return true;
    }
}
