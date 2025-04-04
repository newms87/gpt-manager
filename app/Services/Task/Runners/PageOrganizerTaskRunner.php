<?php

namespace App\Services\Task\Runners;

use App\Models\Agent\AgentThread;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Repositories\ThreadRepository;
use App\Services\JsonSchema\JsonSchemaService;
use App\Services\Task\ArtifactsToGroupsMapper;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\StringHelper;

class PageOrganizerTaskRunner extends AgentThreadTaskRunner
{
    const string RUNNER_NAME = 'Pages Organizer';

    public function run(): void
    {
        $taskDefinition = $this->taskRun->taskDefinition;
        $agent          = $taskDefinition->agent;

        // Make sure to include page numbers in the agent thread so the agent can reference them
        $this->includePageNumbersInThread = true;

        // Setup the thread
        $agentThread       = $this->setupAgentThread();
        $schemaAssociation = $this->taskProcess->outputSchemaAssociation;

        $this->activity("Using agent to organize: $agent->name", 10);
        $jsonSchemaService = app(JsonSchemaService::class)->useArtifactMeta();
        $artifact          = $this->runAgentThreadWithSchema($agentThread, $schemaAssociation?->schemaDefinition, $schemaAssociation?->schemaFragment, $jsonSchemaService);

        // If we didn't receive an artifact from the agent, record the failure
        if (!$artifact) {
            $this->taskProcess->failed_at = now();
            $this->activity("Failed to organize artifacts: No response from agent", 100);

            return;
        }

        $schemaType = $schemaAssociation?->schemaDefinition?->schema['title'] ?? null;

        if ($schemaType) {
            $this->hydrateArtifactJsonContentIds($artifact, $schemaType);
        }

        $artifacts = $this->organizeArtifactIntoGroups($agentThread, $artifact);

        $this->complete($artifacts);
    }

    /**
     * Organize the given artifact into groups based on the schema fragment as the defining grouping key
     */
    public function organizeArtifactIntoGroups(AgentThread $agentThread, Artifact $artifact): array|null
    {
        $fragmentSelector = $this->taskProcess->outputSchemaAssociation->schemaFragment->fragment_selector;

        $groups = app(ArtifactsToGroupsMapper::class)->setGroupingKeys([$fragmentSelector])->map([$artifact]);

        $percentComplete = 35;
        $percentPerGroup = (100 - $percentComplete) / count($groups);

        $organizedArtifacts = [];
        foreach($groups as $artifactsInGroup) {
            $inputArtifact = $artifactsInGroup[0];
            $this->activity("Organizing pages for group of artifact $inputArtifact->id", $percentComplete);

            // Organize the pages for this group
            $results = $this->runOrganizingAgentThread($agentThread, $inputArtifact, $fragmentSelector);

            // If no pages were returned, something went wrong so we can return immediately with a failure
            if (empty($results['pages'])) {
                continue;
            }

            $pages = $results['pages'];
            sort($pages);
            $this->addPagesToArtifact($inputArtifact, $pages);
            $inputArtifact->name = StringHelper::limitText(150, $results['name'], " [pages: " . StringHelper::formatPageRanges($pages) . "]");
            $inputArtifact->save();

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
    public function runOrganizingAgentThread(AgentThread $agentThread, Artifact $inputArtifact, $fragmentSelector = []): array|null
    {
        $filteredInput = app(JsonSchemaService::class)->filterDataByFragmentSelector($inputArtifact->json_content, $fragmentSelector);
        app(ThreadRepository::class)->addMessageToThread(
            $agentThread,
            "List the pages that relate to the group defined by the values in this artifact: " . json_encode($filteredInput) .
            "If there is no clear indicator on a given page that would correlate it to the group, you can assume that page is related to the preceding pages and should be added into the group." .
            " For example if the group is defined by a date and page 1 has the date clearly stated, but page 2 and page 3 do not, include all 3 pages in the group indicated on page 1."
        );

        $schemaDefinition = SchemaDefinition::make([
            'name'   => 'Pages List',
            'schema' => [
                'type'       => 'object',
                'properties' => [
                    'artifact_name' => [
                        'type'        => 'string',
                        'description' => 'Give a name that would best describe this group of pages based on the given values that define this group of pages',
                    ],
                    'pages'         => [
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

        $jsonContent = $outputArtifact->json_content;

        if (empty($jsonContent['pages'])) {
            throw new ValidationError("Pages list artifact must have a 'pages' array");
        }
        if (empty($jsonContent['artifact_name'])) {
            throw new ValidationError("Pages list artifact must have a 'artifact_name' string");
        }

        return [
            'pages' => $jsonContent['pages'],
            'name'  => $jsonContent['artifact_name'],
        ];
    }

    /**
     * Add the given pages to the artifact and save the artifact.
     */
    public function addPagesToArtifact(Artifact $artifact, array $pages): void
    {
        static::log("Add pages to artifact: " . implode(', ', $pages));

        $artifact->json_content = ($artifact->json_content ?? []) + ['pages' => $pages];

        // Keep track of each input artifacts text content for any input artifacts that have a StoredFile matching one of the page numbers in the list
        // NOTE: only record each input artifact once so we do not duplicate the artifacts text.
        // In the case an input artifact has more than 1 matching page, track the minimum page number for that artifact to sort the text content
        $pagesText = [];
        foreach($this->taskProcess->inputArtifacts as $inputArtifact) {
            // If there is no organizable content in this artifact, then skip it
            if (!$inputArtifact->text_content && $inputArtifact->storedFiles->isEmpty()) {
                continue;
            }

            $artifactMinPageNumber = INF;
            $matchingPages         = [];

            if ($inputArtifact->storedFiles->isNotEmpty()) {
                foreach($inputArtifact->storedFiles as $storedFile) {
                    if (in_array($storedFile->page_number, $pages)) {
                        $artifactMinPageNumber = min($storedFile->page_number, $artifactMinPageNumber);
                        static::log("Adding page $storedFile to $artifact");
                        $artifact->storedFiles()->attach($storedFile);
                        $matchingPages[] = [
                            'page_number' => $storedFile->page_number,
                            'file_id'     => $storedFile->id,
                        ];
                    }
                }
            } else {
                if (in_array($inputArtifact->position, $pages)) {
                    $artifactMinPageNumber = $inputArtifact->position;
                    $matchingPages[]       = [
                        'page_number' => $inputArtifact->position,
                    ];
                }
            }

            if ($artifactMinPageNumber !== INF) {
                $pagesText[$artifactMinPageNumber] = [
                    'pages'   => $matchingPages,
                    'content' => $inputArtifact->text_content,
                ];
            }
        }

        ksort($pagesText);

        foreach($pagesText as $match) {
            $pageListStr = '';
            foreach($match['pages'] as $pageItem) {
                $pageListStr .= "### Page $pageItem[page_number]" . (!empty($pageItem['file_id']) ? " (file_id: $pageItem[file_id])" : "") . "\n";
            }
            $artifact->text_content = ($artifact->text_content ? "$artifact->text_content\n\n" : '') . "---\n$pageListStr\n\n" . $match['content'];
        }
        $artifact->save();
    }
}
