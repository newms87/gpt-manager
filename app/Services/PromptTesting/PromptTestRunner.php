<?php

namespace App\Services\PromptTesting;

use App\Models\Agent\Agent;
use App\Models\Agent\McpServer;
use App\Services\PromptTesting\Tests\BasePromptTest;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PromptTestRunner
{
    private ?Agent $agent = null;

    private ?McpServer $mcpServer = null;

    private array $config;

    private ?Command $console = null;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'verbose'      => false,
            'save_results' => false,
            'timeout'      => 120,
        ], $config);

        $this->console = $config['console'] ?? null;
    }

    public function setAgent(Agent $agent): self
    {
        $this->agent = $agent;

        return $this;
    }

    public function setMcpServer(McpServer $mcpServer): self
    {
        $this->mcpServer = $mcpServer;

        return $this;
    }

    public function runTest(string $testName): array
    {
        $startTime = microtime(true);

        try {
            // Load the test class
            $test = $this->loadTest($testName);

            // Set up the test environment
            $test->setUp([
                'agent'      => $this->agent,
                'mcp_server' => $this->mcpServer,
                'config'     => $this->config,
                'console'    => $this->console,
            ]);

            $this->log("Starting test: {$testName}");

            // Run the test
            $result = $test->run();

            $duration = microtime(true) - $startTime;

            $result = array_merge($result, [
                'test_name' => $testName,
                'duration'  => round($duration, 3),
                'timestamp' => Carbon::now()->toISOString(),
                'success'   => $result['success'] ?? false,
            ]);

            $this->log("Test completed in {$duration}s");

            // Save results if requested
            if ($this->config['save_results']) {
                $this->saveResults($result);
            }

            return $result;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;

            $this->log("Test failed: {$e->getMessage()}", 'error');

            return [
                'test_name'        => $testName,
                'duration'         => round($duration, 3),
                'timestamp'        => Carbon::now()->toISOString(),
                'success'          => false,
                'error'            => $e->getMessage(),
                'trace'            => $e->getTraceAsString(),
                'usage'            => null,
                'tool_calls'       => [],
                'response_content' => null,
                'assertions'       => [],
            ];
        }
    }

    private function loadTest(string $testName): BasePromptTest
    {
        // Try different variations of the test name
        $possibleClasses = [
            'App\\Services\\PromptTesting\\Tests\\' . str_replace('/', '\\', $testName) . 'Test',
            'App\\Services\\PromptTesting\\Tests\\' . str_replace('/', '\\', $testName),
            'App\\Services\\PromptTesting\\Tests\\McpServer\\' . $testName . 'Test',
            'App\\Services\\PromptTesting\\Tests\\McpServer\\' . $testName,
        ];

        foreach ($possibleClasses as $className) {
            if (class_exists($className)) {
                $test = new $className();

                if (!$test instanceof BasePromptTest) {
                    throw new Exception("Test class must extend BasePromptTest: {$className}");
                }

                return $test;
            }
        }

        throw new Exception('Test class not found. Tried: ' . implode(', ', $possibleClasses));
    }

    private function saveResults(array $result): void
    {
        // Save to database (could be a dedicated test_results table)
        // For now, just log to file
        $logPath = storage_path('logs/prompt_tests.log');
        file_put_contents($logPath, json_encode($result, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
    }

    private function log(string $message, string $level = 'info'): void
    {
        if ($this->config['verbose'] && $this->console) {
            $this->console->line($message);
        }

        Log::$level("[PromptTest] {$message}");
    }
}
