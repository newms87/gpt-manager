<?php

namespace Tests\Unit\Repositories\ContentSearch;

use App\Models\Agent\Agent;
use App\Models\Prompt\PromptDirective;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskDefinitionDirective;
use App\Repositories\ContentSearch\ContentSearchRepository;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class ContentSearchRepositoryTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    protected ContentSearchRepository $repository;

    protected TaskDefinition $taskDefinition;

    protected Agent $agent;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        $this->repository = new ContentSearchRepository();

        $this->agent = Agent::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Test Agent',
        ]);

        $this->taskDefinition = TaskDefinition::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'agent_id' => $this->agent->id,
            'name'     => 'Test Task Definition',
        ]);
    }

    public function test_getArtifactsForSearch_withTeamId_returnsOnlyTeamArtifacts(): void
    {
        // Given - artifacts from different teams
        $teamArtifact1 = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Team Artifact 1',
        ]);

        $teamArtifact2 = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Team Artifact 2',
        ]);

        $otherTeamArtifact = Artifact::factory()->create([
            'team_id' => 999999,
            'name'    => 'Other Team Artifact',
        ]);

        // When
        $artifacts = $this->repository->getArtifactsForSearch($this->user->currentTeam->id);

        // Then
        $this->assertCount(2, $artifacts);
        $this->assertTrue($artifacts->contains($teamArtifact1));
        $this->assertTrue($artifacts->contains($teamArtifact2));
        $this->assertFalse($artifacts->contains($otherTeamArtifact));
    }

    public function test_getArtifactsForSearch_withArtifactIds_filtersToSpecificArtifacts(): void
    {
        // Given
        $artifact1 = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Artifact 1',
        ]);

        $artifact2 = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Artifact 2',
        ]);

        $artifact3 = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Artifact 3',
        ]);

        // When - only request specific artifacts
        $artifacts = $this->repository->getArtifactsForSearch(
            $this->user->currentTeam->id,
            [$artifact1->id, $artifact3->id]
        );

        // Then
        $this->assertCount(2, $artifacts);
        $this->assertTrue($artifacts->contains($artifact1));
        $this->assertFalse($artifacts->contains($artifact2));
        $this->assertTrue($artifacts->contains($artifact3));
    }

    public function test_getArtifactsWithTextContent_returnsOnlyArtifactsWithText(): void
    {
        // Given
        $artifactWithText = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'This has text content',
        ]);

        $artifactWithEmptyText = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => '',
        ]);

        $artifactWithNullText = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => null,
        ]);

        // When
        $artifacts = $this->repository->getArtifactsWithTextContent($this->user->currentTeam->id);

        // Then
        $this->assertCount(1, $artifacts);
        $this->assertTrue($artifacts->contains($artifactWithText));
        $this->assertFalse($artifacts->contains($artifactWithEmptyText));
        $this->assertFalse($artifacts->contains($artifactWithNullText));
    }

    public function test_getArtifactsWithStructuredData_returnsArtifactsWithMetaOrJsonContent(): void
    {
        // Given
        $artifactWithMeta = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => ['key' => 'value'],
            'json_content' => null,
        ]);

        $artifactWithJsonContent = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => null,
            'json_content' => ['data' => 'value'],
        ]);

        $artifactWithBoth = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => ['meta_key' => 'meta_value'],
            'json_content' => ['json_key' => 'json_value'],
        ]);

        $artifactWithNeither = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'meta'         => null,
            'json_content' => null,
        ]);

        // When
        $artifacts = $this->repository->getArtifactsWithStructuredData($this->user->currentTeam->id);

        // Then
        $this->assertCount(3, $artifacts);
        $this->assertTrue($artifacts->contains($artifactWithMeta));
        $this->assertTrue($artifacts->contains($artifactWithJsonContent));
        $this->assertTrue($artifacts->contains($artifactWithBoth));
        $this->assertFalse($artifacts->contains($artifactWithNeither));
    }

    public function test_getTaskDefinitionDirectives_returnsDirectivesWithText(): void
    {
        // Given
        $directive1 = PromptDirective::factory()->create([
            'name'           => 'Test Directive 1',
            'directive_text' => 'Use this Google Doc: https://docs.google.com/document/d/test1/edit',
        ]);

        $directive2 = PromptDirective::factory()->create([
            'name'           => 'Test Directive 2',
            'directive_text' => 'Another directive text',
        ]);

        $taskDirective1 = TaskDefinitionDirective::create([
            'task_definition_id'  => $this->taskDefinition->id,
            'prompt_directive_id' => $directive1->id,
            'section'             => TaskDefinitionDirective::SECTION_TOP,
            'position'            => 1,
        ]);

        $taskDirective2 = TaskDefinitionDirective::create([
            'task_definition_id'  => $this->taskDefinition->id,
            'prompt_directive_id' => $directive2->id,
            'section'             => TaskDefinitionDirective::SECTION_TOP,
            'position'            => 2,
        ]);

        // When
        $directives = $this->repository->getTaskDefinitionDirectives($this->taskDefinition);

        // Then
        $this->assertCount(2, $directives);
        $this->assertEquals($directive1->directive_text, $directives->first()->directive_text);
        $this->assertEquals($directive2->directive_text, $directives->last()->directive_text);
    }

    public function test_getDirectivesWithText_filtersEmptyDirectives(): void
    {
        // Given
        $directiveWithText = PromptDirective::factory()->create([
            'name'           => 'Directive With Text',
            'directive_text' => 'This directive has text content',
        ]);

        $directiveWithEmptyText = PromptDirective::factory()->create([
            'name'           => 'Empty Directive',
            'directive_text' => '',
        ]);

        $directiveWithNullText = PromptDirective::factory()->create([
            'name'           => 'Null Directive',
            'directive_text' => null,
        ]);

        TaskDefinitionDirective::create([
            'task_definition_id'  => $this->taskDefinition->id,
            'prompt_directive_id' => $directiveWithText->id,
            'section'             => TaskDefinitionDirective::SECTION_TOP,
            'position'            => 1,
        ]);

        TaskDefinitionDirective::create([
            'task_definition_id'  => $this->taskDefinition->id,
            'prompt_directive_id' => $directiveWithEmptyText->id,
            'section'             => TaskDefinitionDirective::SECTION_TOP,
            'position'            => 2,
        ]);

        TaskDefinitionDirective::create([
            'task_definition_id'  => $this->taskDefinition->id,
            'prompt_directive_id' => $directiveWithNullText->id,
            'section'             => TaskDefinitionDirective::SECTION_TOP,
            'position'            => 3,
        ]);

        // When
        $directives = $this->repository->getDirectivesWithText($this->taskDefinition);

        // Then
        $this->assertCount(1, $directives);
        $this->assertEquals('This directive has text content', $directives->first()->directive_text);
    }

    public function test_searchArtifactsByFieldPath_findsArtifactsWithFieldInJsonOrMeta(): void
    {
        // Given
        $artifactWithJsonField = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => ['template_stored_file_id' => 'json-file-id'],
        ]);

        $artifactWithMetaField = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'meta'    => ['template_stored_file_id' => 'meta-file-id'],
        ]);

        $artifactWithoutField = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'json_content' => ['other_field' => 'value'],
        ]);

        // When
        $artifacts = $this->repository->searchArtifactsByFieldPath(
            $this->user->currentTeam->id,
            'template_stored_file_id'
        );

        // Then
        $this->assertCount(2, $artifacts);
        $this->assertTrue($artifacts->contains($artifactWithJsonField));
        $this->assertTrue($artifacts->contains($artifactWithMetaField));
        $this->assertFalse($artifacts->contains($artifactWithoutField));
    }

    public function test_getArtifactForTeam_withValidTeamAndArtifact_returnsArtifact(): void
    {
        // Given
        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Team Artifact',
        ]);

        // When
        $foundArtifact = $this->repository->getArtifactForTeam($artifact->id, $this->user->currentTeam->id);

        // Then
        $this->assertNotNull($foundArtifact);
        $this->assertEquals($artifact->id, $foundArtifact->id);
        $this->assertEquals($artifact->name, $foundArtifact->name);
    }

    public function test_getArtifactForTeam_withWrongTeam_returnsNull(): void
    {
        // Given
        $artifact = Artifact::factory()->create([
            'team_id' => 999999,
            'name'    => 'Other Team Artifact',
        ]);

        // When
        $foundArtifact = $this->repository->getArtifactForTeam($artifact->id, $this->user->currentTeam->id);

        // Then
        $this->assertNull($foundArtifact);
    }

    public function test_getTaskDefinitionForTeam_withValidTeamAndTaskDefinition_returnsTaskDefinition(): void
    {
        // When
        $foundTaskDefinition = $this->repository->getTaskDefinitionForTeam(
            $this->taskDefinition->id,
            $this->user->currentTeam->id
        );

        // Then
        $this->assertNotNull($foundTaskDefinition);
        $this->assertEquals($this->taskDefinition->id, $foundTaskDefinition->id);
        $this->assertEquals($this->taskDefinition->name, $foundTaskDefinition->name);
    }

    public function test_getTaskDefinitionForTeam_withWrongTeam_returnsNull(): void
    {
        // Given - task definition for different team
        $otherTaskDefinition = TaskDefinition::factory()->create([
            'team_id'  => 999999,
            'agent_id' => $this->agent->id,
        ]);

        // When
        $foundTaskDefinition = $this->repository->getTaskDefinitionForTeam(
            $otherTaskDefinition->id,
            $this->user->currentTeam->id
        );

        // Then
        $this->assertNull($foundTaskDefinition);
    }

    public function test_countArtifactsForSearch_returnsCorrectCounts(): void
    {
        // Given
        Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Has text',
            'meta'         => null,
            'json_content' => null,
        ]);

        Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => null,
            'meta'         => ['key' => 'value'],
            'json_content' => null,
        ]);

        Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => null,
            'meta'         => null,
            'json_content' => ['data' => 'value'],
        ]);

        Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Has everything',
            'meta'         => ['meta_key' => 'meta_value'],
            'json_content' => ['json_key' => 'json_value'],
        ]);

        // When
        $counts = $this->repository->countArtifactsForSearch($this->user->currentTeam->id);

        // Then
        $this->assertEquals(4, $counts['total']);
        $this->assertEquals(2, $counts['with_text_content']);
        $this->assertEquals(2, $counts['with_meta']);
        $this->assertEquals(2, $counts['with_json_content']);
        $this->assertEquals(3, $counts['with_structured_data']); // Has meta OR json_content
    }

    public function test_getArtifactTextLengths_returnsCorrectInformation(): void
    {
        // Given
        $artifacts = collect([
            Artifact::factory()->create([
                'team_id'      => $this->user->currentTeam->id,
                'text_content' => 'Short text',
                'meta'         => ['key' => 'value'],
                'json_content' => null,
            ]),
            Artifact::factory()->create([
                'team_id'      => $this->user->currentTeam->id,
                'text_content' => 'This is a much longer text content for testing',
                'meta'         => null,
                'json_content' => ['data' => 'value'],
            ]),
        ]);

        // When
        $lengths = $this->repository->getArtifactTextLengths($artifacts);

        // Then
        $this->assertCount(2, $lengths);
        $this->assertEquals(10, $lengths[0]['text_length']); // "Short text"
        $this->assertTrue($lengths[0]['has_meta']);
        $this->assertFalse($lengths[0]['has_json_content']);

        $this->assertEquals(46, $lengths[1]['text_length']); // Longer text
        $this->assertFalse($lengths[1]['has_meta']);
        $this->assertTrue($lengths[1]['has_json_content']);
    }

    public function test_sortArtifactsByTextLength_sortsCorrectly(): void
    {
        // Given
        $shortArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Short',
            'name'         => 'Short Artifact',
        ]);

        $longArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'This is a much longer text content',
            'name'         => 'Long Artifact',
        ]);

        $mediumArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Medium length text',
            'name'         => 'Medium Artifact',
        ]);

        $artifacts = collect([$longArtifact, $shortArtifact, $mediumArtifact]);

        // When - sort ascending
        $sortedAsc = $this->repository->sortArtifactsByTextLength($artifacts, 'asc');

        // Then
        $this->assertEquals($shortArtifact->id, $sortedAsc->first()->id);
        $this->assertEquals($longArtifact->id, $sortedAsc->last()->id);

        // When - sort descending
        $sortedDesc = $this->repository->sortArtifactsByTextLength($artifacts, 'desc');

        // Then
        $this->assertEquals($longArtifact->id, $sortedDesc->first()->id);
        $this->assertEquals($shortArtifact->id, $sortedDesc->last()->id);
    }

    public function test_getTextSample_truncatesLongText(): void
    {
        // Given
        $longText = str_repeat('This is a long text. ', 20); // 420 characters

        // When
        $sample = $this->repository->getTextSample($longText, 50);

        // Then
        $this->assertEquals(53, strlen($sample)); // 50 + "..."
        $this->assertStringEndsWith('...', $sample);
        $this->assertStringStartsWith('This is a long text.', $sample);
    }

    public function test_getTextSample_returnsFullTextIfShorter(): void
    {
        // Given
        $shortText = 'Short text';

        // When
        $sample = $this->repository->getTextSample($shortText, 50);

        // Then
        $this->assertEquals($shortText, $sample);
        $this->assertStringEndsNotWith('...', $sample);
    }

    public function test_validateTeamAccess_withValidTeamAccess_returnsTrue(): void
    {
        // Given
        $artifacts = collect([
            Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]),
            Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]),
        ]);

        // When
        $isValid = $this->repository->validateTeamAccess(
            $this->user->currentTeam->id,
            $artifacts,
            $this->taskDefinition
        );

        // Then
        $this->assertTrue($isValid);
    }

    public function test_validateTeamAccess_withInvalidTaskDefinitionTeam_returnsFalse(): void
    {
        // Given - task definition from different team
        $wrongTaskDefinition = TaskDefinition::factory()->create([
            'team_id'  => 999999,
            'agent_id' => $this->agent->id,
        ]);

        // When
        $isValid = $this->repository->validateTeamAccess(
            $this->user->currentTeam->id,
            collect([]),
            $wrongTaskDefinition
        );

        // Then
        $this->assertFalse($isValid);
    }

    public function test_validateTeamAccess_withInvalidArtifactTeam_returnsFalse(): void
    {
        // Given - artifacts from different team
        $wrongArtifacts = collect([
            Artifact::factory()->create(['team_id' => $this->user->currentTeam->id]),
            Artifact::factory()->create(['team_id' => 999999]), // Wrong team
        ]);

        // When
        $isValid = $this->repository->validateTeamAccess(
            $this->user->currentTeam->id,
            $wrongArtifacts,
            $this->taskDefinition
        );

        // Then
        $this->assertFalse($isValid);
    }

    public function test_validateTeamAccess_withNullInputs_returnsTrue(): void
    {
        // When - no artifacts or task definition to validate
        $isValid = $this->repository->validateTeamAccess($this->user->currentTeam->id);

        // Then
        $this->assertTrue($isValid);
    }

    public function test_getArtifactsWithPotentialMatches_filtersCorrectly(): void
    {
        // Given
        $matchingArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'This contains a google document link',
        ]);

        $nonMatchingArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'This is just regular text',
        ]);

        $patterns = ['google', 'document'];

        // When
        $artifacts = $this->repository->getArtifactsWithPotentialMatches(
            $this->user->currentTeam->id,
            $patterns
        );

        // Then
        $this->assertCount(1, $artifacts);
        $this->assertTrue($artifacts->contains($matchingArtifact));
        $this->assertFalse($artifacts->contains($nonMatchingArtifact));
    }
}
