<?php

namespace App\Console\Commands;

use App\Models\Agent\Agent;
use App\Models\Agent\McpServer;
use App\Services\PromptTesting\PromptTestRunner;
use Illuminate\Console\Command;

class PromptTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'prompt:test
                           {test? : The test name/path to run (optional - runs all tests if not specified)}
                           {--agent= : Agent ID to use for testing}
                           {--mcp-server= : MCP Server ID to use for testing}
                           {--detailed : Show detailed output}
                           {--save-results : Save test results to database}
                           {--continue-on-failure : Continue running tests even if one fails}';

    /**
     * The console command description.
     */
    protected $description = 'Run prompt engineering tests with real API calls';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $testName          = $this->argument('test');
        $agentId           = $this->option('agent');
        $mcpServerId       = $this->option('mcp-server');
        $verbose           = $this->option('detailed');
        $saveResults       = $this->option('save-results');
        $continueOnFailure = $this->option('continue-on-failure');

        // Initialize the prompt test runner
        $runner = new PromptTestRunner([
            'verbose'      => $verbose,
            'save_results' => $saveResults,
            'console'      => $this,
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

            // If no test specified, run all tests
            if (!$testName) {
                return $this->runAllTests($runner, $continueOnFailure);
            }

            // Run single test
            $this->info("Running prompt test: {$testName}");
            $result = $runner->runTest($testName);

            // Display results
            $this->displayResults($result);

            return $result['success'] ? 0 : 1;

        } catch(\Exception $e) {
            $this->error("Test failed: {$e->getMessage()}");
            if ($verbose) {
                $this->error($e->getTraceAsString());
            }

            return 1;
        }
    }

    /**
     * Run all available tests
     */
    private function runAllTests(PromptTestRunner $runner, bool $continueOnFailure): int
    {
        $this->info("Running all prompt tests...");
        $this->newLine();

        // Get all available tests
        $tests = $this->findAllTests();

        if (empty($tests)) {
            $this->warn('No tests found.');

            return 0;
        }

        $totalTests  = count($tests);
        $passedTests = 0;
        $failedTests = 0;
        $results     = [];

        foreach($tests as $i => $testName) {
            $this->line("Running test " . ($i + 1) . "/{$totalTests}: {$testName}");

            try {
                $result    = $runner->runTest($testName);
                $results[] = $result;

                if ($result['success']) {
                    $this->info("✅ {$testName} PASSED ({$result['duration']}s)");
                    $passedTests++;
                } else {
                    $this->error("❌ {$testName} FAILED ({$result['duration']}s)");
                    $failedTests++;

                    if (!$continueOnFailure) {
                        $this->error("Stopping test execution due to failure. Use --continue-on-failure to run all tests.");
                        break;
                    }
                }
            } catch(\Exception $e) {
                $this->error("❌ {$testName} ERROR: {$e->getMessage()}");
                $failedTests++;

                if (!$continueOnFailure) {
                    $this->error("Stopping test execution due to error. Use --continue-on-failure to run all tests.");
                    break;
                }
            }

            $this->newLine();
        }

        // Display summary
        $this->displayTestSummary($results, $passedTests, $failedTests, $totalTests);

        return $failedTests > 0 ? 1 : 0;
    }

    /**
     * Find all available test classes
     */
    private function findAllTests(): array
    {
        $testPath = app_path('Services/PromptTesting/Tests');
        $tests    = [];

        if (!is_dir($testPath)) {
            return $tests;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($testPath)
        );

        foreach($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($testPath . '/', '', $file->getPathname());
            $className    = str_replace(['/', '.php'], ['\\', ''], $relativePath);

            if ($className === 'BasePromptTest') {
                continue;
            }

            // Convert class name back to test name
            $testName = str_replace('Test', '', $className);
            $tests[]  = $testName;
        }

        sort($tests);

        return $tests;
    }

    /**
     * Display comprehensive test summary
     */
    private function displayTestSummary(array $results, int $passed, int $failed, int $total): void
    {
        $this->line('=== Test Summary ===');

        if ($failed > 0) {
            $this->error("❌ {$failed} test(s) failed");
        }
        if ($passed > 0) {
            $this->info("✅ {$passed} test(s) passed");
        }

        $this->line("Total: {$total} tests");

        // Calculate aggregate metrics
        $totalDuration = array_sum(array_column($results, 'duration'));
        $totalTokens   = array_sum(array_column($results, 'input_tokens')) + array_sum(array_column($results, 'output_tokens'));
        $totalCost     = array_sum(array_column($results, 'total_cost'));

        $this->table(['Metric', 'Value'], [
            ['Total Duration', round($totalDuration, 2) . 's'],
            ['Total Tokens', number_format($totalTokens)],
            ['Total Cost', '$' . number_format($totalCost, 4)],
            ['Success Rate', round(($passed / $total) * 100, 1) . '%'],
        ]);

        if ($this->option('detailed') && !empty($results)) {
            $this->newLine();
            $this->line('=== Individual Test Results ===');
            foreach($results as $result) {
                $status = $result['success'] ? '✅' : '❌';
                $this->line("{$status} {$result['test_name']} - {$result['duration']}s");
            }
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
            if (isset($result['error'])) {
                $this->error("Error: {$result['error']}");
            }
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
            foreach($result['assertions'] as $assertion) {
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
            foreach($result['tool_calls'] as $i => $toolCall) {
                $this->line("Tool Call " . ($i + 1) . ": {$toolCall['tool_name']}");
                if ($this->option('detailed')) {
                    $this->line("  Arguments: " . json_encode($toolCall['arguments'], JSON_PRETTY_PRINT));
                    $this->line("  Result: " . json_encode($toolCall['result'], JSON_PRETTY_PRINT));
                }
            }
        }
    }
}
