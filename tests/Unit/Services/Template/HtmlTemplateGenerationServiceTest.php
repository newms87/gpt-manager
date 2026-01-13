<?php

namespace Tests\Unit\Services\Template;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadMessage;
use App\Models\Agent\AgentThreadRun;
use App\Models\Template\TemplateDefinition;
use App\Models\Template\TemplateVariable;
use App\Services\AgentThread\AgentThreadService;
use App\Services\Template\HtmlTemplateGenerationService;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class HtmlTemplateGenerationServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        // Prevent actual job dispatching
        Queue::fake();
    }

    #[Test]
    public function processLlmResponse_withValidJsonContent_updatesTemplateHtmlAndCss(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'user_id'      => $this->user->id,
            'type'         => TemplateDefinition::TYPE_HTML,
            'html_content' => null,
            'css_content'  => null,
        ]);

        $thread  = AgentThread::factory()->create();
        $message = AgentThreadMessage::factory()->create([
            'agent_thread_id' => $thread->id,
            'role'            => AgentThreadMessage::ROLE_ASSISTANT,
            'content'         => json_encode([
                'html_content'       => '<div data-var-customer_name>Customer Name</div>',
                'css_content'        => '.template { color: blue; }',
                'variable_names'     => ['customer_name'],
                'screenshot_request' => false,
            ]),
        ]);

        $run = AgentThreadRun::factory()->create([
            'agent_thread_id' => $thread->id,
            'last_message_id' => $message->id,
            'started_at'      => now()->subMinutes(1),
            'completed_at'    => now(),
        ]);

        // When
        $result = app(HtmlTemplateGenerationService::class)->processLlmResponse($run, $template);

        // Then
        $this->assertEquals('success', $result['status']);
        $this->assertNull($result['screenshot_request']);

        $template->refresh();
        $this->assertEquals('<div data-var-customer_name>Customer Name</div>', $template->html_content);
        $this->assertEquals('.template { color: blue; }', $template->css_content);
    }

    #[Test]
    public function processLlmResponse_withVariableNames_createsTemplateVariableRecords(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'user_id'      => $this->user->id,
            'type'         => TemplateDefinition::TYPE_HTML,
            'html_content' => null,
            'css_content'  => null,
        ]);

        $thread  = AgentThread::factory()->create();
        $message = AgentThreadMessage::factory()->create([
            'agent_thread_id' => $thread->id,
            'role'            => AgentThreadMessage::ROLE_ASSISTANT,
            'content'         => json_encode([
                'html_content'       => '<div data-var-customer_name>Name</div><span data-var-order_total>$0.00</span>',
                'css_content'        => '.template { }',
                'variable_names'     => ['customer_name', 'order_total', 'invoice_date'],
                'screenshot_request' => false,
            ]),
        ]);

        $run = AgentThreadRun::factory()->create([
            'agent_thread_id' => $thread->id,
            'last_message_id' => $message->id,
            'started_at'      => now()->subMinutes(1),
            'completed_at'    => now(),
        ]);

        // When
        $result = app(HtmlTemplateGenerationService::class)->processLlmResponse($run, $template);

        // Then
        $this->assertEquals('success', $result['status']);
        $this->assertEquals(3, $result['variables_synced']);

        // Verify variables were created
        $variables = $template->templateVariables()->get();
        $this->assertCount(3, $variables);

        $variableNames = $variables->pluck('name')->toArray();
        $this->assertContains('customer_name', $variableNames);
        $this->assertContains('order_total', $variableNames);
        $this->assertContains('invoice_date', $variableNames);

        // Verify variables have correct default values
        $customerNameVar = $variables->firstWhere('name', 'customer_name');
        $this->assertEquals(TemplateVariable::MAPPING_TYPE_AI, $customerNameVar->mapping_type);
        $this->assertEquals(TemplateVariable::STRATEGY_FIRST, $customerNameVar->multi_value_strategy);
        $this->assertEquals('Customer Name', $customerNameVar->description);
    }

    #[Test]
    public function processLlmResponse_withExistingVariables_onlyCreatesNewOnes(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'user_id'      => $this->user->id,
            'type'         => TemplateDefinition::TYPE_HTML,
            'html_content' => null,
            'css_content'  => null,
        ]);

        // Create an existing variable
        TemplateVariable::factory()->create([
            'template_definition_id' => $template->id,
            'name'                   => 'customer_name',
            'description'            => 'Existing description',
            'mapping_type'           => TemplateVariable::MAPPING_TYPE_ARTIFACT,
        ]);

        $thread  = AgentThread::factory()->create();
        $message = AgentThreadMessage::factory()->create([
            'agent_thread_id' => $thread->id,
            'role'            => AgentThreadMessage::ROLE_ASSISTANT,
            'content'         => json_encode([
                'html_content'       => '<div>Test</div>',
                'css_content'        => '.test { }',
                'variable_names'     => ['customer_name', 'order_total'],
                'screenshot_request' => false,
            ]),
        ]);

        $run = AgentThreadRun::factory()->create([
            'agent_thread_id' => $thread->id,
            'last_message_id' => $message->id,
            'started_at'      => now()->subMinutes(1),
            'completed_at'    => now(),
        ]);

        // When
        $result = app(HtmlTemplateGenerationService::class)->processLlmResponse($run, $template);

        // Then
        $this->assertEquals('success', $result['status']);
        $this->assertEquals(1, $result['variables_synced']); // Only 1 new variable created

        $variables = $template->templateVariables()->get();
        $this->assertCount(2, $variables);

        // Verify existing variable was not modified
        $existingVar = $variables->firstWhere('name', 'customer_name');
        $this->assertEquals('Existing description', $existingVar->description);
        $this->assertEquals(TemplateVariable::MAPPING_TYPE_ARTIFACT, $existingVar->mapping_type);
    }

    #[Test]
    public function processLlmResponse_withScreenshotRequest_storesRequestInMessageData(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'user_id'      => $this->user->id,
            'type'         => TemplateDefinition::TYPE_HTML,
            'html_content' => null,
            'css_content'  => null,
        ]);

        $thread  = AgentThread::factory()->create();
        $message = AgentThreadMessage::factory()->create([
            'agent_thread_id' => $thread->id,
            'role'            => AgentThreadMessage::ROLE_ASSISTANT,
            'content'         => json_encode([
                'html_content'       => '<div>Test</div>',
                'css_content'        => '.test { }',
                'variable_names'     => [],
                'screenshot_request' => [
                    'id'     => 'screenshot_123',
                    'status' => 'pending',
                    'reason' => 'I need to see how the layout renders',
                ],
            ]),
            'data' => null,
        ]);

        $run = AgentThreadRun::factory()->create([
            'agent_thread_id' => $thread->id,
            'last_message_id' => $message->id,
            'started_at'      => now()->subMinutes(1),
            'completed_at'    => now(),
        ]);

        // When
        $result = app(HtmlTemplateGenerationService::class)->processLlmResponse($run, $template);

        // Then
        $this->assertEquals('success', $result['status']);
        $this->assertNotNull($result['screenshot_request']);
        $this->assertEquals('screenshot_123', $result['screenshot_request']['id']);
        $this->assertEquals('pending', $result['screenshot_request']['status']);
        $this->assertEquals('I need to see how the layout renders', $result['screenshot_request']['reason']);

        // Verify screenshot request was stored in message data
        $message->refresh();
        $this->assertArrayHasKey('screenshot_request', $message->data);
        $this->assertEquals('pending', $message->data['screenshot_request']['status']);
        $this->assertArrayHasKey('requested_at', $message->data['screenshot_request']);
    }

    #[Test]
    public function processLlmResponse_withUncompletedRun_returnsError(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'user_id'      => $this->user->id,
            'type'         => TemplateDefinition::TYPE_HTML,
            'html_content' => 'original content',
            'css_content'  => 'original css',
        ]);

        $thread  = AgentThread::factory()->create();
        $message = AgentThreadMessage::factory()->create([
            'agent_thread_id' => $thread->id,
            'role'            => AgentThreadMessage::ROLE_ASSISTANT,
            'content'         => json_encode([
                'html_content' => '<div>New Content</div>',
                'css_content'  => '.new { }',
            ]),
        ]);

        // Run that failed (completed_at is null)
        $run = AgentThreadRun::factory()->create([
            'agent_thread_id' => $thread->id,
            'last_message_id' => $message->id,
            'started_at'      => now()->subMinutes(1),
            'failed_at'       => now(),
        ]);

        // When
        $result = app(HtmlTemplateGenerationService::class)->processLlmResponse($run, $template);

        // Then
        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Thread run did not complete successfully', $result['error']);
        $this->assertNull($result['screenshot_request']);
        $this->assertEquals(0, $result['variables_synced']);

        // Verify template was not updated
        $template->refresh();
        $this->assertEquals('original content', $template->html_content);
        $this->assertEquals('original css', $template->css_content);
    }

    #[Test]
    public function processLlmResponse_withPlainTextResponse_doesNotUpdateTemplate(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'user_id'      => $this->user->id,
            'type'         => TemplateDefinition::TYPE_HTML,
            'html_content' => 'original content',
            'css_content'  => 'original css',
        ]);

        $thread  = AgentThread::factory()->create();
        $message = AgentThreadMessage::factory()->create([
            'agent_thread_id' => $thread->id,
            'role'            => AgentThreadMessage::ROLE_ASSISTANT,
            'content'         => 'This is not valid JSON at all', // Plain text response
        ]);

        $run = AgentThreadRun::factory()->create([
            'agent_thread_id' => $thread->id,
            'last_message_id' => $message->id,
            'started_at'      => now()->subMinutes(1),
            'completed_at'    => now(),
        ]);

        // When
        $result = app(HtmlTemplateGenerationService::class)->processLlmResponse($run, $template);

        // Then - getJsonContent returns {'text_content': ...} which is a valid array but lacks html_content
        // So template should not be updated, but result is still "success" (no html_content to extract)
        $this->assertEquals('success', $result['status']);
        $this->assertEquals(0, $result['variables_synced']);

        // Verify template was not updated (no html_content or css_content in response)
        $template->refresh();
        $this->assertEquals('original content', $template->html_content);
        $this->assertEquals('original css', $template->css_content);
    }

    #[Test]
    public function processLlmResponse_withNullMessage_returnsError(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'user_id'      => $this->user->id,
            'type'         => TemplateDefinition::TYPE_HTML,
        ]);

        $thread = AgentThread::factory()->create();

        $run = AgentThreadRun::factory()->create([
            'agent_thread_id' => $thread->id,
            'last_message_id' => null, // No message
            'started_at'      => now()->subMinutes(1),
            'completed_at'    => now(),
        ]);

        // When
        $result = app(HtmlTemplateGenerationService::class)->processLlmResponse($run, $template);

        // Then
        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Invalid response format from LLM', $result['error']);
    }

    #[Test]
    public function processLlmResponse_withOnlyCssChange_updatesOnlyCss(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'user_id'      => $this->user->id,
            'type'         => TemplateDefinition::TYPE_HTML,
            'html_content' => '<div>Existing HTML</div>',
            'css_content'  => '.old { color: red; }',
        ]);

        $thread  = AgentThread::factory()->create();
        $message = AgentThreadMessage::factory()->create([
            'agent_thread_id' => $thread->id,
            'role'            => AgentThreadMessage::ROLE_ASSISTANT,
            'content'         => json_encode([
                'html_content'       => '<div>Existing HTML</div>', // Same HTML
                'css_content'        => '.new { color: blue; }',   // Different CSS
                'variable_names'     => [],
                'screenshot_request' => false,
            ]),
        ]);

        $run = AgentThreadRun::factory()->create([
            'agent_thread_id' => $thread->id,
            'last_message_id' => $message->id,
            'started_at'      => now()->subMinutes(1),
            'completed_at'    => now(),
        ]);

        // When
        $result = app(HtmlTemplateGenerationService::class)->processLlmResponse($run, $template);

        // Then
        $this->assertEquals('success', $result['status']);

        $template->refresh();
        $this->assertEquals('<div>Existing HTML</div>', $template->html_content);
        $this->assertEquals('.new { color: blue; }', $template->css_content);
    }

    #[Test]
    public function processLlmResponse_withNoChanges_doesNotTriggerSave(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'user_id'      => $this->user->id,
            'type'         => TemplateDefinition::TYPE_HTML,
            'html_content' => '<div>Same HTML</div>',
            'css_content'  => '.same { color: red; }',
        ]);

        $originalUpdatedAt = $template->updated_at;

        $thread  = AgentThread::factory()->create();
        $message = AgentThreadMessage::factory()->create([
            'agent_thread_id' => $thread->id,
            'role'            => AgentThreadMessage::ROLE_ASSISTANT,
            'content'         => json_encode([
                'html_content'       => '<div>Same HTML</div>',    // Same HTML
                'css_content'        => '.same { color: red; }',  // Same CSS
                'variable_names'     => [],
                'screenshot_request' => false,
            ]),
        ]);

        $run = AgentThreadRun::factory()->create([
            'agent_thread_id' => $thread->id,
            'last_message_id' => $message->id,
            'started_at'      => now()->subMinutes(1),
            'completed_at'    => now(),
        ]);

        // When
        $result = app(HtmlTemplateGenerationService::class)->processLlmResponse($run, $template);

        // Then
        $this->assertEquals('success', $result['status']);

        // Verify template was not re-saved (updated_at unchanged)
        $template->refresh();
        $this->assertEquals($originalUpdatedAt->toDateTimeString(), $template->updated_at->toDateTimeString());
    }

    #[Test]
    public function processLlmResponse_extractsVariablesFromHtmlWhenNotProvidedInResponse(): void
    {
        // Given
        $template = TemplateDefinition::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'user_id'      => $this->user->id,
            'type'         => TemplateDefinition::TYPE_HTML,
            'html_content' => null,
            'css_content'  => null,
        ]);

        $thread  = AgentThread::factory()->create();
        $message = AgentThreadMessage::factory()->create([
            'agent_thread_id' => $thread->id,
            'role'            => AgentThreadMessage::ROLE_ASSISTANT,
            'content'         => json_encode([
                'html_content'       => '<div data-var-first_name>First</div><span data-var-last_name>Last</span>',
                'css_content'        => '.test { }',
                'variable_names'     => [], // Empty - should extract from HTML
                'screenshot_request' => false,
            ]),
        ]);

        $run = AgentThreadRun::factory()->create([
            'agent_thread_id' => $thread->id,
            'last_message_id' => $message->id,
            'started_at'      => now()->subMinutes(1),
            'completed_at'    => now(),
        ]);

        // When
        $result = app(HtmlTemplateGenerationService::class)->processLlmResponse($run, $template);

        // Then
        $this->assertEquals('success', $result['status']);
        $this->assertEquals(2, $result['variables_synced']);

        $variables = $template->templateVariables()->pluck('name')->toArray();
        $this->assertContains('first_name', $variables);
        $this->assertContains('last_name', $variables);
    }

    #[Test]
    public function sendMessage_callsProcessLlmResponseAfterThreadRunCompletes(): void
    {
        // Given
        $agent = Agent::factory()->create([
            'team_id' => null,
            'name'    => 'Template Builder',
        ]);

        $template = TemplateDefinition::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'user_id'      => $this->user->id,
            'type'         => TemplateDefinition::TYPE_HTML,
            'html_content' => null,
            'css_content'  => null,
        ]);

        $thread = AgentThread::factory()->create([
            'agent_id'             => $agent->id,
            'collaboratable_type'  => TemplateDefinition::class,
            'collaboratable_id'    => $template->id,
        ]);

        // Mock AgentThreadService to return a completed run with response
        $mockRun = AgentThreadRun::factory()->create([
            'agent_thread_id' => $thread->id,
            'started_at'      => now()->subMinutes(1),
            'completed_at'    => now(),
        ]);

        // Create the response message
        $responseMessage = AgentThreadMessage::factory()->create([
            'agent_thread_id' => $thread->id,
            'role'            => AgentThreadMessage::ROLE_ASSISTANT,
            'content'         => json_encode([
                'html_content'       => '<div data-var-test_var>Test</div>',
                'css_content'        => '.test { color: green; }',
                'variable_names'     => ['test_var'],
                'screenshot_request' => false,
            ]),
        ]);

        $mockRun->update(['last_message_id' => $responseMessage->id]);

        // Mock the AgentThreadService to return our pre-built run
        $this->mock(AgentThreadService::class, function ($mock) use ($mockRun) {
            $mock->shouldReceive('withTimeout')
                ->andReturnSelf();
            $mock->shouldReceive('run')
                ->andReturn($mockRun);
        });

        // When
        $result = app(HtmlTemplateGenerationService::class)->sendMessage($thread, 'Please update the template');

        // Then - verify the run was returned and template was updated
        $this->assertInstanceOf(AgentThreadRun::class, $result);

        $template->refresh();
        $this->assertEquals('<div data-var-test_var>Test</div>', $template->html_content);
        $this->assertEquals('.test { color: green; }', $template->css_content);

        // Verify variable was synced
        $variables = $template->templateVariables()->pluck('name')->toArray();
        $this->assertContains('test_var', $variables);
    }

    #[Test]
    public function sendMessage_doesNotProcessResponseWhenRunFails(): void
    {
        // Given
        $agent = Agent::factory()->create([
            'team_id' => null,
            'name'    => 'Template Builder',
        ]);

        $template = TemplateDefinition::factory()->create([
            'team_id'      => $this->user->currentTeam->id,
            'user_id'      => $this->user->id,
            'type'         => TemplateDefinition::TYPE_HTML,
            'html_content' => 'original html',
            'css_content'  => 'original css',
        ]);

        $thread = AgentThread::factory()->create([
            'agent_id'             => $agent->id,
            'collaboratable_type'  => TemplateDefinition::class,
            'collaboratable_id'    => $template->id,
        ]);

        // Mock a failed run
        $mockRun = AgentThreadRun::factory()->create([
            'agent_thread_id' => $thread->id,
            'started_at'      => now()->subMinutes(1),
            'failed_at'       => now(),
        ]);

        $this->mock(AgentThreadService::class, function ($mock) use ($mockRun) {
            $mock->shouldReceive('withTimeout')
                ->andReturnSelf();
            $mock->shouldReceive('run')
                ->andReturn($mockRun);
        });

        // When
        $result = app(HtmlTemplateGenerationService::class)->sendMessage($thread, 'Please update the template');

        // Then - template should not be updated
        $this->assertInstanceOf(AgentThreadRun::class, $result);

        $template->refresh();
        $this->assertEquals('original html', $template->html_content);
        $this->assertEquals('original css', $template->css_content);
    }

    #[Test]
    public function handleScreenshotResponse_updatesMessageDataWithScreenshotCompletion(): void
    {
        // Given
        $thread  = AgentThread::factory()->create();
        $message = AgentThreadMessage::factory()->create([
            'agent_thread_id' => $thread->id,
            'role'            => AgentThreadMessage::ROLE_ASSISTANT,
            'content'         => '{}',
            'data'            => [
                'screenshot_request' => [
                    'id'           => 'screenshot_456',
                    'status'       => 'pending',
                    'reason'       => 'Need to see the layout',
                    'requested_at' => now()->subMinutes(1)->toIso8601String(),
                ],
            ],
        ]);

        $screenshot = \Newms87\Danx\Models\Utilities\StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'filename' => 'screenshot.png',
            'mime'     => 'image/png',
        ]);

        // When
        app(HtmlTemplateGenerationService::class)->handleScreenshotResponse($message, $screenshot);

        // Then
        $message->refresh();
        $this->assertEquals('completed', $message->data['screenshot_request']['status']);
        $this->assertEquals($screenshot->id, $message->data['screenshot_request']['screenshot_id']);
        $this->assertArrayHasKey('completed_at', $message->data['screenshot_request']);
    }
}
