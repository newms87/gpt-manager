<?php

namespace App\Services\Task\Runners;

use App\Models\Agent\AgentThread;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Repositories\ThreadRepository;
use App\Services\JsonSchema\JsonSchemaService;
use App\Services\Task\ArtifactsToGroupsMapper;

class PageOrganizerTaskRunner extends AgentThreadTaskRunner
{
    const string RUNNER_NAME = 'Pages Organizer';

    public function run(): void
    {
        $taskDefinitionAgent = $this->taskProcess->taskDefinitionAgent;
        $agent               = $taskDefinitionAgent->agent;

        // Make sure to include page numbers in the agent thread so the agent can reference them
        $this->includePageNumbersInThread = true;

        // Setup the thread
        $agentThread       = $this->setupAgentThread();
        $schemaAssociation = $this->taskProcess->taskDefinitionAgent->outputSchemaAssociation;

        $this->activity("Using agent to organize: $agent->name", 10);
        $jsonSchemaService = app(JsonSchemaService::class)->useDbFields();
        $artifact          = $this->runAgentThreadWithSchema($agentThread, $schemaAssociation?->schemaDefinition, $schemaAssociation?->schemaFragment, $jsonSchemaService);

        // If we didn't receive an artifact from the agent, record the failure
        if (!$artifact) {
            $this->taskProcess->failed_at = now();
            $this->activity("Failed to organize artifacts: No response from agent", 100);

            return;
        }

        $artifacts = $this->organizeArtifactIntoGroups($agentThread, $artifact);

        $this->complete($artifacts);
    }

    /**
     * Organize the given artifact into groups based on the schema fragment as the defining grouping key
     */
    public function organizeArtifactIntoGroups(AgentThread $agentThread, Artifact $artifact): array|null
    {
        $taskDefinitionAgent = $this->taskProcess->taskDefinitionAgent;
        $fragmentSelector    = $taskDefinitionAgent->outputSchemaAssociation->schemaFragment->fragment_selector;

        $groups = app(ArtifactsToGroupsMapper::class)->setGroupingKeys([$fragmentSelector])->map([$artifact]);

        $percentComplete = 35;
        $percentPerGroup = (100 - $percentComplete) / count($groups);

        $organizedArtifacts = [];
        foreach($groups as $artifactsInGroup) {
            $inputArtifact = $artifactsInGroup[0];
            $this->activity("Organizing pages for group of artifact $inputArtifact->id", $percentComplete);

            // Organize the pages for this group
            $pages = $this->runOrganizingAgentThread($agentThread, $inputArtifact);

            // If no pages were returned, something went wrong so we can return immediately with a failure
            if (!$pages) {
                continue;
            }

            $this->addPagesToArtifact($inputArtifact, $pages);

            $organizedArtifacts[] = $inputArtifact;
            $percentComplete      += $percentPerGroup;
        }

        $this->activity("Pages have been organized into " . count($organizedArtifacts) . " artifacts", 100);

        return $organizedArtifacts;
    }

    /**
     * Run the agent thread to organize the pages for a group.
     * This will run the agent thread with the original thread and append the given artifact that defines the grouping.
     * The agent will be asked to identify all the pages related to the given grouping and return that list of pages.
     *
     * @return array|null The list of pages that belong to the group
     */
    public function runOrganizingAgentThread(AgentThread $agentThread, Artifact $inputArtifact): array|null
    {
        app(ThreadRepository::class)->addMessageToThread(
            $agentThread,
            "List the pages that relate to the group defined by the values in this artifact: " . json_encode($inputArtifact->json_content)
        );

        $schemaDefinition = SchemaDefinition::make([
            'name'   => 'Pages List',
            'schema' => [
                'type'       => 'object',
                'properties' => [
                    'pages' => [
                        'type'        => 'array',
                        'description' => 'The list of page numbers for the pages that belong to the group',
                        'items'       => ['type' => 'number'],
                    ],
                ],
            ],
        ]);

        $outputArtifact = $this->runAgentThreadWithSchema($agentThread, $schemaDefinition);

        // If we didn't receive an artifact from the agent, record the failure
        if (!$outputArtifact) {
            $this->taskProcess->failed_at = now();
            $this->activity("Failed to organize artifacts: No response from agent for artifact $inputArtifact->id", 100);

            return null;
        }

        return $outputArtifact->json_content['pages'];
    }

    /**
     * Add the given pages to the artifact and save the artifact.
     */
    public function addPagesToArtifact(Artifact $artifact, array $pages): void
    {
        static::log("Add pages to artifact: " . implode(', ', $pages));

        $artifact->json_content = ($artifact->json_content ?? []) + ['pages' => $pages];

        foreach($this->taskProcess->inputArtifacts as $inputArtifact) {
            foreach($inputArtifact->storedFiles as $storedFile) {
                if (in_array($storedFile->page_number, $pages)) {
                    static::log("Adding page $storedFile to $artifact");
                    $artifact->storedFiles()->attach($storedFile);
                    $artifact->text_content = ($artifact->text_content ? "$artifact->text_content\n\n" : '') . "---\n### Page $storedFile->page_number (file_id: $storedFile->id)\n\n" . $inputArtifact->text_content;
                    continue 2;
                }
            }
        }
        $artifact->save();
    }
}
