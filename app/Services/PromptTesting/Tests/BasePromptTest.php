<?php

namespace App\Services\PromptTesting\Tests;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Agent\McpServer;
use App\Services\AgentThread\AgentThreadService;
use Newms87\Danx\Traits\HasDebugLogging;
use Exception;
use Illuminate\Console\Command;

abstract class BasePromptTest
{
    use HasDebugLogging;

    protected ?Agent $agent = null;

    protected ?McpServer $mcpServer = null;

    protected array $config = [];

    protected ?Command $console = null;

    protected array $assertions = [];

    public function setUp(array $config): void
    {
        $this->agent     = $config['agent']      ?? null;
        $this->mcpServer = $config['mcp_server'] ?? null;
        $this->config    = $config['config']     ?? [];
        $this->console   = $config['console']    ?? null;
    }

    abstract public function run(): array;

    protected function createThread(string $prompt, array $options = []): AgentThread
    {
        if (!$this->agent) {
            throw new Exception('Agent is required for creating threads');
        }

        // Create a new thread
        $thread = AgentThread::create([
            'agent_id' => $this->agent->id,
            'name'     => $options['name'] ?? 'Prompt Test Thread',
            'team_id'  => $this->agent->team_id,
        ]);

        // Add the initial prompt message
        $thread->messages()->create([
            'role'    => 'user',
            'content' => $prompt,
        ]);

        return $thread;
    }

    protected function runThread(AgentThread $thread): array
    {
        $service = new AgentThreadService();

        // Add MCP server if available
        if ($this->mcpServer) {
            $service->withMcpServer($this->mcpServer);
        }

        $this->log("Running thread with agent: {$this->agent->name}");
        if ($this->mcpServer) {
            $this->log("Using MCP server: {$this->mcpServer->name}");
        }

        // Run the thread
        $threadRun = $service->run($thread);

        // Extract response data
        $lastMessage     = $threadRun->lastMessage;
        $responseContent = $lastMessage ? $lastMessage->content : '';

        return [
            'thread_run'       => $threadRun,
            'response_content' => $responseContent,
            'usage'            => $threadRun->usageSummary ? [
                'total_tokens'  => $threadRun->usageSummary->total_tokens,
                'input_tokens'  => $threadRun->usageSummary->input_tokens,
                'output_tokens' => $threadRun->usageSummary->output_tokens,
                'total_cost'    => $threadRun->usageSummary->total_cost,
            ] : null,
            'tool_calls' => $this->extractToolCalls($lastMessage),
        ];
    }

    protected function extractToolCalls($message): array
    {
        if (!$message || !$message->data) {
            return [];
        }

        $toolCalls = [];
        $data      = is_array($message->data) ? $message->data : json_decode($message->data, true);

        if (isset($data['tool_calls'])) {
            foreach ($data['tool_calls'] as $toolCall) {
                $toolCalls[] = [
                    'tool_name' => $toolCall['function']['name']      ?? 'unknown',
                    'arguments' => $toolCall['function']['arguments'] ?? [],
                    'result'    => $toolCall['result']                ?? null,
                ];
            }
        }

        return $toolCalls;
    }

    protected function assert(bool $condition, string $description, string $errorMessage = ''): void
    {
        $passed = $condition;

        $this->assertions[] = [
            'passed'      => $passed,
            'description' => $description,
            'error'       => $passed ? null : $errorMessage,
        ];

        if ($passed) {
            $this->log("✅ {$description}");
        } else {
            $this->log("❌ {$description}: {$errorMessage}");
        }
    }

    protected function assertContains(string $needle, string $haystack, string $description): void
    {
        $contains = str_contains($haystack, $needle);
        $this->assert(
            $contains,
            $description,
            "Expected '{$needle}' to be found in response"
        );
    }

    protected function assertNotEmpty($value, string $description): void
    {
        $this->assert(
            !empty(trim($value ?? '')),
            $description,
            'Expected non-empty value, got: ' . var_export($value, true)
        );
    }

    protected function assertToolCalled(array $toolCalls, string $toolName, string $description): void
    {
        $called = false;
        foreach ($toolCalls as $toolCall) {
            if ($toolCall['tool_name'] === $toolName) {
                $called = true;
                break;
            }
        }

        $this->assert(
            $called,
            $description,
            "Expected tool '{$toolName}' to be called"
        );
    }

    protected function log(string $message): void
    {
        if ($this->config['verbose'] && $this->console) {
            $this->console->line($message);
        }

        static::logInfo($message);
    }

    protected function getTestResults(): array
    {
        $success = true;
        foreach ($this->assertions as $assertion) {
            if (!$assertion['passed']) {
                $success = false;
                break;
            }
        }

        return [
            'success'    => $success,
            'assertions' => $this->assertions,
        ];
    }
}
