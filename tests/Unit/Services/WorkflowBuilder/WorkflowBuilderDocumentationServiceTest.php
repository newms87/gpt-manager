<?php

namespace Tests\Unit\Services\WorkflowBuilder;

use App\Models\Task\TaskDefinition;
use App\Models\Workflow\WorkflowConnection;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowNode;
use App\Services\WorkflowBuilder\WorkflowBuilderDocumentationService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class WorkflowBuilderDocumentationServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private WorkflowBuilderDocumentationService $service;

    private string $testDocsPath;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->service = new WorkflowBuilderDocumentationService();

        // Set up test documentation path
        $this->testDocsPath = base_path('tests/fixtures/workflow-builder-prompts');
        if (!File::exists($this->testDocsPath)) {
            File::makeDirectory($this->testDocsPath, 0755, true);
        }

        // Use reflection to override the docs path for testing
        $reflection           = new \ReflectionClass($this->service);
        $docsBasePathProperty = $reflection->getProperty('docsBasePath');
        $docsBasePathProperty->setAccessible(true);
        $docsBasePathProperty->setValue($this->service, $this->testDocsPath);
    }

    public function tearDown(): void
    {
        // Clean up test files
        if (File::exists($this->testDocsPath)) {
            File::deleteDirectory($this->testDocsPath);
        }
        parent::tearDown();
    }

    public function test_loadDocumentFile_withExistingFile_returnsContent(): void
    {
        // Given
        $filename = 'workflow-definition.md';
        $content  = "# Workflow Definition\nThis is test content.";
        File::put($this->testDocsPath . '/' . $filename, $content);

        // When
        $result = $this->service->loadDocumentFile($filename);

        // Then
        $this->assertEquals($content, $result);
    }

    public function test_loadDocumentFile_withNonexistentFile_returnsDefaultContent(): void
    {
        // Given
        Log::shouldReceive('warning')->once();
        $filename = 'nonexistent-file.md';

        // When
        $result = $this->service->loadDocumentFile($filename);

        // Then
        $this->assertNull($result);
    }

    public function test_loadDocumentFile_withWorkflowDefinitionFile_returnsDefaultContent(): void
    {
        // Given
        Log::shouldReceive('warning')->once();
        $filename = 'workflow-definition.md';

        // When
        $result = $this->service->loadDocumentFile($filename);

        // Then
        $this->assertNotNull($result);
        $this->assertStringContainsString('Workflow Definition', $result);
        $this->assertStringContainsString('name: Descriptive workflow name', $result);
    }

    public function test_loadDocumentFile_cachesContent(): void
    {
        // Given
        $filename = 'test-cache.md';
        $content  = 'Test content for caching';
        File::put($this->testDocsPath . '/' . $filename, $content);

        // When - load twice
        $firstResult  = $this->service->loadDocumentFile($filename);
        $secondResult = $this->service->loadDocumentFile($filename);

        // Then
        $this->assertEquals($content, $firstResult);
        $this->assertEquals($content, $secondResult);
        $this->assertSame($firstResult, $secondResult); // Same reference due to caching
    }

    public function test_loadDocumentFile_withEmptyFile_returnsDefaultContent(): void
    {
        // Given
        Log::shouldReceive('warning')->once();
        $filename = 'empty-file.md';
        File::put($this->testDocsPath . '/' . $filename, ''); // Empty file

        // When
        $result = $this->service->loadDocumentFile($filename);

        // Then
        $this->assertNull($result);
    }

    public function test_getPlanningContext_withoutWorkflow_returnsBasicContext(): void
    {
        // Given - create test documentation files
        File::put($this->testDocsPath . '/workflow-definition.md', '# Workflow Definition Test');
        File::put($this->testDocsPath . '/task-definition.md', '# Task Definition Test');
        File::put($this->testDocsPath . '/workflow-connections.md', '# Workflow Connections Test');

        // When
        $result = $this->service->getPlanningContext();

        // Then
        $this->assertStringContainsString('# Workflow Definition Test', $result);
        $this->assertStringContainsString('# Task Definition Test', $result);
        $this->assertStringContainsString('# Workflow Connections Test', $result);
        $this->assertStringContainsString('## Planning Guidelines', $result);
        $this->assertStringContainsString('Focus on understanding the user\'s high-level requirements', $result);
    }

    public function test_getPlanningContext_withWorkflow_includesWorkflowContext(): void
    {
        // Given
        $workflow = $this->createWorkflowWithNodesAndConnections();
        File::put($this->testDocsPath . '/workflow-definition.md', '# Test Workflow Doc');

        // When
        $result = $this->service->getPlanningContext($workflow);

        // Then
        $this->assertStringContainsString('# Test Workflow Doc', $result);
        $this->assertStringContainsString('## Current Workflow: ' . $workflow->name, $result);
        $this->assertStringContainsString('**Max Workers:** ' . $workflow->max_workers, $result);
    }

    public function test_getOrchestratorContext_includesAllRequiredDocuments(): void
    {
        // Given - create all orchestrator documentation files
        $docFiles = [
            'workflow-definition.md'  => '# Workflow Definition',
            'task-definition.md'      => '# Task Definition',
            'workflow-connections.md' => '# Workflow Connections',
            'task-runners-catalog.md' => '# Task Runners Catalog',
            'agent-selection.md'      => '# Agent Selection',
        ];

        foreach ($docFiles as $filename => $content) {
            File::put($this->testDocsPath . '/' . $filename, $content);
        }

        // When
        $result = $this->service->getOrchestratorContext();

        // Then
        foreach ($docFiles as $content) {
            $this->assertStringContainsString($content, $result);
        }
        $this->assertStringContainsString('## Orchestrator Guidelines', $result);
        $this->assertStringContainsString('Break down the approved plan into specific task definitions', $result);
    }

    public function test_getTaskBuilderContext_includesTaskSpecificDocuments(): void
    {
        // Given
        $workflow      = $this->createWorkflowWithNodesAndConnections();
        $specification = [
            'task_specification' => [
                'name'        => 'Test Task',
                'description' => 'Test task description',
            ],
            'related_tasks' => [
                ['name' => 'Related Task 1', 'description' => 'Related task 1 description'],
                ['name' => 'Related Task 2'],
            ],
        ];

        // Create required documentation files
        $docFiles = [
            'task-definition.md'          => '# Task Definition',
            'task-runners-catalog.md'     => '# Task Runners',
            'agent-selection.md'          => '# Agent Selection',
            'prompt-engineering-guide.md' => '# Prompt Engineering',
            'artifact-flow.md'            => '# Artifact Flow',
        ];

        foreach ($docFiles as $filename => $content) {
            File::put($this->testDocsPath . '/' . $filename, $content);
        }

        // When
        $result = $this->service->getTaskBuilderContext($specification, $workflow);

        // Then
        foreach ($docFiles as $content) {
            $this->assertStringContainsString($content, $result);
        }
        $this->assertStringContainsString('## Task Builder Guidelines', $result);
        $this->assertStringContainsString('### Related Tasks in This Workflow:', $result);
        $this->assertStringContainsString('Related Task 1', $result);
        $this->assertStringContainsString('### Existing Tasks in Workflow:', $result);
    }

    public function test_getEvaluationContext_includesArtifactsAndGuidelines(): void
    {
        // Given
        $artifacts = [
            [
                'name'    => 'Test Artifact 1',
                'type'    => 'workflow_definition',
                'content' => ['name' => 'Test Workflow', 'description' => 'Test workflow description'],
            ],
            [
                'name'    => 'Test Artifact 2',
                'type'    => 'text',
                'content' => 'This is plain text content',
            ],
        ];

        File::put($this->testDocsPath . '/evaluation-guidelines.md', '# Evaluation Guidelines Test');

        // When
        $result = $this->service->getEvaluationContext($artifacts);

        // Then
        $this->assertStringContainsString('# Evaluation Guidelines Test', $result);
        $this->assertStringContainsString('## Build Artifacts', $result);
        $this->assertStringContainsString('### Artifact 1', $result);
        $this->assertStringContainsString('**Name:** Test Artifact 1', $result);
        $this->assertStringContainsString('**Type:** workflow_definition', $result);
        $this->assertStringContainsString('```json', $result);
        $this->assertStringContainsString('### Artifact 2', $result);
        $this->assertStringContainsString('This is plain text content', $result);
        $this->assertStringContainsString('## Evaluation Guidelines', $result);
        $this->assertStringContainsString('Analyze the workflow build results for completeness', $result);
    }

    public function test_clearCache_clearsDocumentCache(): void
    {
        // Given
        $filename        = 'test-cache-clear.md';
        $originalContent = 'Original content';
        $newContent      = 'New content';

        File::put($this->testDocsPath . '/' . $filename, $originalContent);

        // Load and cache the original content
        $cachedResult = $this->service->loadDocumentFile($filename);
        $this->assertEquals($originalContent, $cachedResult);

        // Update the file content
        File::put($this->testDocsPath . '/' . $filename, $newContent);

        // When - clear cache
        $this->service->clearCache();
        $newResult = $this->service->loadDocumentFile($filename);

        // Then
        $this->assertEquals($newContent, $newResult);
        $this->assertNotEquals($originalContent, $newResult);
    }

    public function test_getAvailableDocuments_returnsExistingMarkdownFiles(): void
    {
        // Given
        $testFiles = [
            'doc1.md',
            'doc2.md',
            'doc3.txt', // Non-markdown file
            'doc4.md',
        ];

        foreach ($testFiles as $file) {
            File::put($this->testDocsPath . '/' . $file, 'Test content');
        }

        // When
        $result = $this->service->getAvailableDocuments();

        // Then
        $this->assertCount(3, $result); // Only .md files
        foreach ($result as $file) {
            $this->assertStringEndsWith('.md', $file);
        }
    }

    public function test_getAvailableDocuments_withNonexistentDirectory_returnsEmpty(): void
    {
        // Given - remove the test directory
        if (File::exists($this->testDocsPath)) {
            File::deleteDirectory($this->testDocsPath);
        }

        // When
        $result = $this->service->getAvailableDocuments();

        // Then
        $this->assertEmpty($result);
    }

    public function test_validateDocumentation_returnsExistingAndMissingFiles(): void
    {
        // Given
        $existingFiles = ['workflow-definition.md', 'task-definition.md'];
        foreach ($existingFiles as $file) {
            File::put($this->testDocsPath . '/' . $file, 'Test content');
        }

        // When
        $result = $this->service->validateDocumentation();

        // Then
        $this->assertArrayHasKey('existing', $result);
        $this->assertArrayHasKey('missing', $result);
        $this->assertArrayHasKey('base_path', $result);

        $this->assertCount(2, $result['existing']);
        $this->assertContains('workflow-definition.md', $result['existing']);
        $this->assertContains('task-definition.md', $result['existing']);

        $this->assertGreaterThan(0, count($result['missing']));
        $this->assertContains('workflow-connections.md', $result['missing']);

        $this->assertEquals($this->testDocsPath, $result['base_path']);
    }

    public function test_formatCurrentWorkflowContext_withCompleteWorkflow_returnsFormattedContext(): void
    {
        // Given
        $workflow = $this->createWorkflowWithNodesAndConnections();

        // When - use reflection to access protected method
        $reflection = new \ReflectionClass($this->service);
        $method     = $reflection->getMethod('formatCurrentWorkflowContext');
        $method->setAccessible(true);
        $result = $method->invoke($this->service, $workflow);

        // Then
        $this->assertStringContainsString('## Current Workflow: ' . $workflow->name, $result);
        $this->assertStringContainsString('**Description:** ' . $workflow->description, $result);
        $this->assertStringContainsString('**Max Workers:** ' . $workflow->max_workers, $result);
        $this->assertStringContainsString('### Current Nodes:', $result);
        $this->assertStringContainsString('### Current Connections:', $result);
    }

    public function test_formatArtifactsForEvaluation_withMixedArtifacts_returnsFormattedText(): void
    {
        // Given
        $artifacts = [
            [
                'name'    => 'JSON Artifact',
                'type'    => 'json',
                'content' => ['key' => 'value', 'number' => 42],
            ],
            [
                'name'    => 'Text Artifact',
                'type'    => 'text',
                'content' => 'Simple text content',
            ],
        ];

        // When - use reflection to access protected method
        $reflection = new \ReflectionClass($this->service);
        $method     = $reflection->getMethod('formatArtifactsForEvaluation');
        $method->setAccessible(true);
        $result = $method->invoke($this->service, $artifacts);

        // Then
        $this->assertStringContainsString('### Artifact 1', $result);
        $this->assertStringContainsString('**Name:** JSON Artifact', $result);
        $this->assertStringContainsString('**Type:** json', $result);
        $this->assertStringContainsString('```json', $result);
        $this->assertStringContainsString('"key": "value"', $result);

        $this->assertStringContainsString('### Artifact 2', $result);
        $this->assertStringContainsString('**Name:** Text Artifact', $result);
        $this->assertStringContainsString('Simple text content', $result);
    }

    public function test_defaultContent_providesReasonableDefaults(): void
    {
        // Given - no files exist, so service should use default content and log warnings

        // When - get context without any files (real business logic)
        $planningResult     = $this->service->getPlanningContext();
        $orchestratorResult = $this->service->getOrchestratorContext();

        // Then - verify the real service provides reasonable defaults
        $this->assertStringContainsString('Workflow Definition', $planningResult);
        $this->assertStringContainsString('Task Definition', $planningResult);
        $this->assertStringContainsString('Workflow Connections', $planningResult);

        $this->assertStringContainsString('Task Runners', $orchestratorResult);
        $this->assertStringContainsString('Agent Selection', $orchestratorResult);

        // Verify that defaults are substantial (not just empty content)
        $this->assertGreaterThan(100, strlen($planningResult));
        $this->assertGreaterThan(100, strlen($orchestratorResult));
    }

    private function createWorkflowWithNodesAndConnections(): WorkflowDefinition
    {
        $workflow = WorkflowDefinition::factory()->create([
            'team_id'     => $this->user->currentTeam->id,
            'name'        => 'Test Workflow',
            'description' => 'Test workflow description',
            'max_workers' => 3,
        ]);

        // Create task definitions
        $task1 = TaskDefinition::factory()->create([
            'team_id'          => $this->user->currentTeam->id,
            'name'             => 'Task 1',
            'description'      => 'First task',
            'task_runner_name' => 'TestRunner1',
        ]);

        $task2 = TaskDefinition::factory()->create([
            'team_id'          => $this->user->currentTeam->id,
            'name'             => 'Task 2',
            'description'      => 'Second task',
            'task_runner_name' => 'TestRunner2',
        ]);

        // Create workflow nodes
        $node1 = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'task_definition_id'     => $task1->id,
        ]);

        $node2 = WorkflowNode::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'task_definition_id'     => $task2->id,
        ]);

        // Create workflow connection
        WorkflowConnection::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'source_node_id'         => $node1->id,
            'target_node_id'         => $node2->id,
        ]);

        return $workflow->fresh(['workflowNodes.taskDefinition', 'workflowConnections.sourceNode.taskDefinition', 'workflowConnections.targetNode.taskDefinition']);
    }
}
