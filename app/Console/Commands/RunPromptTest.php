<?php

namespace App\Console\Commands;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Agent\McpServer;
use App\Services\AgentThread\AgentThreadService;
use App\Services\PromptTesting\PromptTestRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunPromptTest extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'prompt:test 
                           {test : The test name/path to run}
                           {--agent= : Agent ID to use for testing}
                           {--mcp-server= : MCP Server ID to use for testing}
                           {--detailed : Show detailed output}
                           {--save-results : Save test results to database}';

    /**
     * The console command description.
     */
    protected $description = 'Run prompt engineering tests with real API calls';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $testName = $this->argument('test');
        $agentId = $this->option('agent');
        $mcpServerId = $this->option('mcp-server');
        $verbose = $this->option('detailed');
        $saveResults = $this->option('save-results');

        // Initialize the prompt test runner
        $runner = new PromptTestRunner([
            'verbose' => $verbose,
            'save_results' => $saveResults,
            'console' => $this,
        ]);

        try {
            // Load agent if specified
            if ($agentId) {
                $agent = Agent::findOrFail($agentId);
                $runner->setAgent($agent);
                $this->info("Using agent: {$agent->name} ({$agent->model})");
            }

            // Load MCP server if specified
            if ($mcpServerId) {
                $mcpServer = McpServer::findOrFail($mcpServerId);
                $runner->setMcpServer($mcpServer);
                $this->info("Using MCP server: {$mcpServer->name}");
            }

            // Run the test
            $this->info("Running prompt test: {$testName}");
            $result = $runner->runTest($testName);

            // Display results
            $this->displayResults($result);

            return $result['success'] ? 0 : 1;

        } catch (\Exception $e) {
            $this->error("Test failed: {$e->getMessage()}");
            if ($verbose) {
                $this->error($e->getTraceAsString());
            }
            return 1;
        }
    }

    /**
     * Display test results in a formatted way
     */
    private function displayResults(array $result): void
    {
        $this->newLine();
        $this->line('=== Test Results ===');
        
        if ($result['success']) {
            $this->info('✅ Test PASSED');
        } else {
            $this->error('❌ Test FAILED');
        }

        $this->table(['Metric', 'Value'], [
            ['Test Name', $result['test_name']],
            ['Duration', $result['duration'] . 's'],
            ['Input Tokens', $result['input_tokens'] ?? 'N/A'],
            ['Output Tokens', $result['output_tokens'] ?? 'N/A'],
            ['Total Cost', $result['total_cost'] ? '$' . number_format($result['total_cost'], 4) : 'N/A'],
        ]);

        if (isset($result['assertions'])) {
            $this->newLine();
            $this->line('=== Assertions ===');
            foreach ($result['assertions'] as $assertion) {
                $status = $assertion['passed'] ? '✅' : '❌';
                $this->line("{$status} {$assertion['description']}");
                if (!$assertion['passed'] && isset($assertion['error'])) {
                    $this->error("   Error: {$assertion['error']}");
                }
            }
        }

        if (isset($result['response_content']) && $this->option('detailed')) {
            $this->newLine();
            $this->line('=== Response Content ===');
            $this->line($result['response_content']);
        }

        if (isset($result['tool_calls']) && !empty($result['tool_calls'])) {
            $this->newLine();
            $this->line('=== Tool Calls ===');
            foreach ($result['tool_calls'] as $i => $toolCall) {
                $this->line("Tool Call " . ($i + 1) . ": {$toolCall['tool_name']}");
                if ($this->option('detailed')) {
                    $this->line("  Arguments: " . json_encode($toolCall['arguments'], JSON_PRETTY_PRINT));
                    $this->line("  Result: " . json_encode($toolCall['result'], JSON_PRETTY_PRINT));
                }
            }
        }
    }
}