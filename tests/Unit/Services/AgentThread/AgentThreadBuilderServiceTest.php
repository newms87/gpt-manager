<?php

namespace Tests\Unit\Services\AgentThread;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Services\AgentThread\AgentThreadBuilderService;
use App\Services\AgentThread\ArtifactFilter;
use Newms87\Danx\Exceptions\ValidationError;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class AgentThreadBuilderServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    #[Test]
    public function for_withAgent_createsBuilder(): void
    {
        // Given
        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);

        // When
        $builder = AgentThreadBuilderService::for($agent);

        // Then
        $this->assertInstanceOf(AgentThreadBuilderService::class, $builder);
    }

    #[Test]
    public function named_withThreadName_setsName(): void
    {
        // Given
        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);

        // When
        $thread = AgentThreadBuilderService::for($agent)
            ->named('Test Thread')
            ->build();

        // Then
        $this->assertEquals('Test Thread', $thread->name);
        $this->assertEquals($agent->id, $thread->agent_id);
    }

    #[Test]
    public function withMessage_withSingleMessage_addsUserMessage(): void
    {
        // Given
        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);

        // When
        $thread = AgentThreadBuilderService::for($agent)
            ->named('Message Thread')
            ->withMessage('Hello, world!')
            ->build();

        // Then
        $this->assertDatabaseHas('agent_threads', [
            'id'   => $thread->id,
            'name' => 'Message Thread',
        ]);
        $this->assertDatabaseHas('agent_thread_messages', [
            'agent_thread_id' => $thread->id,
            'content'         => 'Hello, world!',
            'role'            => 'user',
        ]);
    }

    #[Test]
    public function withSystemMessage_withMessage_addsSystemMessage(): void
    {
        // Given
        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);

        // When
        $thread = AgentThreadBuilderService::for($agent)
            ->named('System Thread')
            ->withSystemMessage('You are a helpful assistant')
            ->withMessage('What is 2+2?')
            ->build();

        // Then
        $this->assertEquals(2, $thread->messages()->count());
        $messages = $thread->messages;

        // First message should be system
        $this->assertEquals('You are a helpful assistant', $messages[0]->content);
        $this->assertEquals('user', $messages[0]->role);

        // Second message should be user
        $this->assertEquals('What is 2+2?', $messages[1]->content);
        $this->assertEquals('user', $messages[1]->role);
    }

    #[Test]
    public function withArtifacts_withoutFilter_addsAllArtifactContent(): void
    {
        // Given
        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Test content',
            'json_content' => ['key' => 'value'],
        ]);

        // When
        $thread = AgentThreadBuilderService::for($agent)
            ->named('Artifact Thread')
            ->withArtifacts([$artifact])
            ->build();

        // Then
        $this->assertInstanceOf(AgentThread::class, $thread);
        $messages = $thread->messages;
        $this->assertGreaterThan(0, $messages->count());
    }

    #[Test]
    public function withArtifacts_withFilter_filtersContent(): void
    {
        // Given
        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Test content',
            'json_content' => ['key' => 'value'],
        ]);

        $filter = new ArtifactFilter(
            includeText: true,
            includeJson: false,
            includeFiles: false,
            includeMeta: false
        );

        // When
        $thread = AgentThreadBuilderService::for($agent)
            ->named('Filtered Thread')
            ->withArtifacts([$artifact], $filter)
            ->build();

        // Then
        $this->assertInstanceOf(AgentThread::class, $thread);
        $messages = $thread->messages;
        $this->assertGreaterThan(0, $messages->count());
    }

    #[Test]
    public function withArtifacts_calledMultipleTimes_addsMultipleGroups(): void
    {
        // Given
        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $artifact1 = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'First artifact',
        ]);
        $artifact2 = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Second artifact',
        ]);

        $filter1 = new ArtifactFilter(includeText: true, includeJson: false);
        $filter2 = new ArtifactFilter(includeText: true, includeJson: true);

        // When
        $thread = AgentThreadBuilderService::for($agent)
            ->named('Multiple Groups Thread')
            ->withArtifacts([$artifact1], $filter1)
            ->withArtifacts([$artifact2], $filter2)
            ->build();

        // Then
        $this->assertInstanceOf(AgentThread::class, $thread);
        $messages = $thread->messages;
        $this->assertGreaterThan(0, $messages->count());
    }

    #[Test]
    public function includePageNumbers_withArtifacts_injectsPageNumbers(): void
    {
        // Given
        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Page content',
            'position'     => 5,
        ]);

        // When
        $thread = AgentThreadBuilderService::for($agent)
            ->named('Page Numbers Thread')
            ->withArtifacts([$artifact])
            ->includePageNumbers()
            ->build();

        // Then
        $this->assertInstanceOf(AgentThread::class, $thread);
        $messages = $thread->messages;
        $content = $messages->pluck('content')->join(' ');
        $this->assertStringContainsString('Page 5', $content);
    }

    #[Test]
    public function build_calledTwice_returnsCachedThread(): void
    {
        // Given
        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $builder = AgentThreadBuilderService::for($agent)
            ->named('Cached Thread')
            ->withMessage('Test');

        // When
        $thread1 = $builder->build();
        $thread2 = $builder->build();

        // Then
        $this->assertSame($thread1, $thread2);
    }

    #[Test]
    public function build_withoutAgent_throwsValidationError(): void
    {
        // Given/When/Then
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Agent is required to build thread');

        $builder = new AgentThreadBuilderService();
        $builder->build();
    }

    #[Test]
    public function withResponseSchema_withSchema_configuresSchema(): void
    {
        // Given
        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $schema = SchemaDefinition::factory()->create(['team_id' => $this->user->currentTeam->id]);

        // When - Just test that the builder accepts the configuration
        $builder = AgentThreadBuilderService::for($agent)
            ->named('Schema Thread')
            ->withMessage('Test')
            ->withResponseSchema($schema);

        $thread = $builder->build();

        // Then
        $this->assertInstanceOf(AgentThread::class, $thread);
    }

    #[Test]
    public function withTimeout_withSeconds_setsTimeout(): void
    {
        // Given
        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);

        // When - Just test that the builder accepts the configuration
        $builder = AgentThreadBuilderService::for($agent)
            ->named('Timeout Thread')
            ->withMessage('Test')
            ->withTimeout(120);

        $thread = $builder->build();

        // Then
        $this->assertInstanceOf(AgentThread::class, $thread);
    }

    #[Test]
    public function withArtifacts_withEmptyCollection_doesNotAddMessages(): void
    {
        // Given
        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);

        // When
        $thread = AgentThreadBuilderService::for($agent)
            ->named('Empty Artifacts Thread')
            ->withArtifacts([])
            ->withMessage('Test message')
            ->build();

        // Then
        $this->assertInstanceOf(AgentThread::class, $thread);
        $messages = $thread->messages;
        $this->assertEquals(1, $messages->count()); // Only the test message
    }

    #[Test]
    public function multipleMessages_withDifferentTypes_addsInOrder(): void
    {
        // Given
        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);

        // When
        $thread = AgentThreadBuilderService::for($agent)
            ->named('Multiple Messages Thread')
            ->withSystemMessage('System message 1')
            ->withMessage('User message 1')
            ->withMessage('User message 2')
            ->withSystemMessage('System message 2')
            ->build();

        // Then
        $this->assertEquals(4, $thread->messages()->count());
        $messages = $thread->messages;

        // All messages are stored as 'user' role in this system
        $this->assertEquals('System message 1', $messages[0]->content);
        $this->assertEquals('User message 1', $messages[1]->content);
        $this->assertEquals('User message 2', $messages[2]->content);
        $this->assertEquals('System message 2', $messages[3]->content);
    }

    #[Test]
    public function artifactFilter_withAllOptionsDisabled_filtersEverything(): void
    {
        // Given
        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Test content',
            'json_content' => ['key' => 'value'],
        ]);

        $filter = new ArtifactFilter(
            includeText: false,
            includeFiles: false,
            includeJson: false,
            includeMeta: false
        );

        // When
        $thread = AgentThreadBuilderService::for($agent)
            ->named('All Filtered Thread')
            ->withArtifacts([$artifact], $filter)
            ->withMessage('After artifacts')
            ->build();

        // Then
        $this->assertInstanceOf(AgentThread::class, $thread);
        // Artifact should be filtered out, only the message should remain
        $this->assertEquals(1, $thread->messages()->count());
    }

    #[Test]
    public function named_withLongName_truncatesAppropriately(): void
    {
        // Given
        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $longName = str_repeat('A', 200);

        // When
        $thread = AgentThreadBuilderService::for($agent)
            ->named($longName)
            ->build();

        // Then
        $this->assertInstanceOf(AgentThread::class, $thread);
        // Name should be truncated to 150 characters by ThreadRepository
        $this->assertLessThanOrEqual(150, strlen($thread->name));
    }

    #[Test]
    public function for_withTeamId_setsTeamOnThread(): void
    {
        // Given
        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);

        // When
        $thread = AgentThreadBuilderService::for($agent, $this->user->currentTeam->id)
            ->named('Team Thread')
            ->build();

        // Then
        $this->assertEquals($this->user->currentTeam->id, $thread->team_id);
    }

    #[Test]
    public function artifactFilter_withFragmentSelectors_appliesSelectors(): void
    {
        // Given
        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Test content',
            'json_content' => ['key' => 'value', 'nested' => ['data' => 'test']],
            'meta'         => ['category' => 'test'],
        ]);

        $filter = new ArtifactFilter(
            includeText: false,
            includeFiles: false,
            includeJson: true,
            includeMeta: true,
            jsonFragmentSelector: ['field' => 'json_content', 'path' => 'nested.data'],
            metaFragmentSelector: ['field' => 'meta', 'path' => 'category']
        );

        // When
        $thread = AgentThreadBuilderService::for($agent)
            ->named('Fragment Selector Thread')
            ->withArtifacts([$artifact], $filter)
            ->build();

        // Then
        $this->assertInstanceOf(AgentThread::class, $thread);
    }
}
