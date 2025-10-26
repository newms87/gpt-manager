<?php

namespace Tests\Unit\Services\Task;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Task\Artifact;
use App\Models\Task\TaskArtifactFilter;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskDefinitionDirective;
use App\Models\Task\TaskRun;
use App\Services\Task\TaskAgentThreadBuilderService;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class TaskAgentThreadBuilderServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    #[Test]
    public function fromTaskDefinition_withTaskDefinition_includesDirectivesAndPrompt(): void
    {
        // Given
        $agent          = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'agent_id' => $agent->id,
            'prompt'   => 'Process these items',
        ]);

        // Create directives manually
        $beforeDirectiveModel = \App\Models\Prompt\PromptDirective::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'directive_text' => 'Before directive',
        ]);

        $afterDirectiveModel = \App\Models\Prompt\PromptDirective::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'directive_text' => 'After directive',
        ]);

        // Create task definition directives
        $beforeDirective = TaskDefinitionDirective::create([
            'task_definition_id'  => $taskDefinition->id,
            'section'             => 'Top',
            'position'            => 1,
            'prompt_directive_id' => $beforeDirectiveModel->id,
        ]);

        $afterDirective = TaskDefinitionDirective::create([
            'task_definition_id'  => $taskDefinition->id,
            'section'             => 'Bottom',
            'position'            => 2,
            'prompt_directive_id' => $afterDirectiveModel->id,
        ]);

        // When
        $thread = TaskAgentThreadBuilderService::fromTaskDefinition($taskDefinition)
            ->build();

        // Then
        $this->assertInstanceOf(AgentThread::class, $thread);
        $messages   = $thread->messages;
        $allContent = $messages->pluck('content')->join(' ');

        $this->assertStringContainsString('Before directive', $allContent);
        $this->assertStringContainsString('Process these items', $allContent);
        $this->assertStringContainsString('After directive', $allContent);
    }

    #[Test]
    public function fromTaskDefinition_withTaskRun_setsTaskRunContext(): void
    {
        // Given
        $agent          = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'agent_id' => $agent->id,
        ]);
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);

        // When
        $thread = TaskAgentThreadBuilderService::fromTaskDefinition($taskDefinition, $taskRun)
            ->build();

        // Then
        $this->assertInstanceOf(AgentThread::class, $thread);
    }

    #[Test]
    public function withArtifacts_withTaskArtifactFilter_convertsToArtifactFilter(): void
    {
        // Given
        $agent = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);

        // Create source and target task definitions
        $sourceTaskDefinition = TaskDefinition::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'agent_id' => $agent->id,
        ]);

        $targetTaskDefinition = TaskDefinition::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'agent_id' => $agent->id,
        ]);

        // Create artifact with source task definition
        $artifact = Artifact::factory()->create([
            'team_id'            => $this->user->currentTeam->id,
            'text_content'       => 'Test content',
            'task_definition_id' => $sourceTaskDefinition->id,
        ]);

        // Create task artifact filter
        $taskFilter = TaskArtifactFilter::factory()->create([
            'source_task_definition_id' => $sourceTaskDefinition->id,
            'target_task_definition_id' => $targetTaskDefinition->id,
            'include_text'              => true,
            'include_files'             => false,
            'include_json'              => false,
            'include_meta'              => false,
        ]);

        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $targetTaskDefinition->id,
        ]);

        // When
        $thread = TaskAgentThreadBuilderService::fromTaskDefinition($targetTaskDefinition, $taskRun)
            ->withArtifacts([$artifact])
            ->build();

        // Then
        $this->assertInstanceOf(AgentThread::class, $thread);
        $this->assertGreaterThan(0, $thread->messages()->count());
    }

    #[Test]
    public function withContextArtifacts_withNoContext_addsOnlyPrimaryArtifacts(): void
    {
        // Given
        $agent          = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'agent_id' => $agent->id,
        ]);

        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Primary content',
            'position'     => 5,
        ]);

        // When
        $thread = TaskAgentThreadBuilderService::fromTaskDefinition($taskDefinition)
            ->withContextArtifacts([$artifact], [])
            ->build();

        // Then
        $this->assertInstanceOf(AgentThread::class, $thread);
        $messages   = $thread->messages;
        $allContent = $messages->pluck('content')->join(' ');

        $this->assertStringContainsString('PRIMARY ARTIFACTS', $allContent);
        $this->assertStringNotContainsString('CONTEXT BEFORE', $allContent);
        $this->assertStringNotContainsString('CONTEXT AFTER', $allContent);
    }

    #[Test]
    public function withContextArtifacts_withBeforeAndAfter_groupsByPosition(): void
    {
        // Given
        $agent          = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'agent_id' => $agent->id,
        ]);

        // Primary artifacts at positions 10-20
        $primaryArtifacts = [
            Artifact::factory()->create([
                'team_id'      => $this->user->currentTeam->id,
                'text_content' => 'Primary 1',
                'position'     => 10,
            ]),
            Artifact::factory()->create([
                'team_id'      => $this->user->currentTeam->id,
                'text_content' => 'Primary 2',
                'position'     => 20,
            ]),
        ];

        // Context artifacts before, between, and after
        $contextArtifacts = [
            Artifact::factory()->create([
                'team_id'      => $this->user->currentTeam->id,
                'text_content' => 'Context before',
                'position'     => 5,
            ]),
            Artifact::factory()->create([
                'team_id'      => $this->user->currentTeam->id,
                'text_content' => 'Context after',
                'position'     => 25,
            ]),
        ];

        // When
        $thread = TaskAgentThreadBuilderService::fromTaskDefinition($taskDefinition)
            ->withContextArtifacts($primaryArtifacts, $contextArtifacts)
            ->build();

        // Then
        $this->assertInstanceOf(AgentThread::class, $thread);
        $messages   = $thread->messages;
        $allContent = $messages->pluck('content')->join(' ');

        $this->assertStringContainsString('CONTEXT BEFORE', $allContent);
        $this->assertStringContainsString('PRIMARY ARTIFACTS', $allContent);
        $this->assertStringContainsString('CONTEXT AFTER', $allContent);

        // Verify order by checking positions in the combined content
        $beforePos  = strpos($allContent, 'CONTEXT BEFORE');
        $primaryPos = strpos($allContent, 'PRIMARY ARTIFACTS');
        $afterPos   = strpos($allContent, 'CONTEXT AFTER');

        $this->assertLessThan($primaryPos, $beforePos);
        $this->assertLessThan($afterPos, $primaryPos);
    }

    #[Test]
    public function withContextArtifacts_withOnlyBefore_addsOnlyBeforeContext(): void
    {
        // Given
        $agent          = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'agent_id' => $agent->id,
        ]);

        $primaryArtifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Primary',
            'position'     => 10,
        ]);

        $contextBefore = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Context before',
            'position'     => 5,
        ]);

        // When
        $thread = TaskAgentThreadBuilderService::fromTaskDefinition($taskDefinition)
            ->withContextArtifacts([$primaryArtifact], [$contextBefore])
            ->build();

        // Then
        $this->assertInstanceOf(AgentThread::class, $thread);
        $messages   = $thread->messages;
        $allContent = $messages->pluck('content')->join(' ');

        $this->assertStringContainsString('CONTEXT BEFORE', $allContent);
        $this->assertStringContainsString('PRIMARY ARTIFACTS', $allContent);
        $this->assertStringNotContainsString('CONTEXT AFTER', $allContent);
    }

    #[Test]
    public function named_withCustomName_setsThreadName(): void
    {
        // Given
        $agent          = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'agent_id' => $agent->id,
        ]);

        // When
        $thread = TaskAgentThreadBuilderService::fromTaskDefinition($taskDefinition)
            ->named('Custom Thread Name')
            ->build();

        // Then
        $this->assertEquals('Custom Thread Name', $thread->name);
    }

    #[Test]
    public function includePageNumbers_withArtifacts_injectsPageNumbers(): void
    {
        // Given
        $agent          = Agent::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $taskDefinition = TaskDefinition::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'agent_id' => $agent->id,
        ]);

        $artifact = Artifact::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'text_content' => 'Page content',
            'position'     => 7,
        ]);

        // When
        $thread = TaskAgentThreadBuilderService::fromTaskDefinition($taskDefinition)
            ->withArtifacts([$artifact])
            ->includePageNumbers()
            ->build();

        // Then
        $this->assertInstanceOf(AgentThread::class, $thread);
        $messages = $thread->messages;
        $content  = $messages->pluck('content')->join(' ');
        $this->assertStringContainsString('Page 7', $content);
    }
}
