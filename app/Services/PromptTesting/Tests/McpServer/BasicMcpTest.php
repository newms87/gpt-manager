<?php

namespace App\Services\PromptTesting\Tests\McpServer;

use App\Services\PromptTesting\Tests\BasePromptTest;

class BasicMcpTest extends BasePromptTest
{
    public function run(): array
    {
        // Test basic MCP server functionality
        $prompt = $this->getPrompt();

        // Create and run the thread
        $thread = $this->createThread($prompt, [
            'name' => 'Basic MCP Test',
        ]);

        $result = $this->runThread($thread);

        // Perform assertions
        $this->runAssertions($result);

        return array_merge($result, $this->getTestResults());
    }

    private function getPrompt(): string
    {
        return <<<'PROMPT'
List all available tools from the MCP server and explain what each tool does.
PROMPT;
    }

    private function runAssertions(array $result): void
    {
        // Assert that we got a response
        $this->assertNotEmpty(
            $result['response_content'],
            'Response should not be empty'
        );

        // Assert that the response mentions tools
        $this->assertContains(
            'tool',
            strtolower($result['response_content']),
            'Response should mention available tools'
        );

        // Check for token usage
        $this->assert(
            $result['input_tokens'] > 0,
            'Input tokens should be greater than 0',
            'No input tokens recorded'
        );

        $this->assert(
            $result['output_tokens'] > 0,
            'Output tokens should be greater than 0',
            'No output tokens recorded'
        );

        // Log the raw response for debugging
        $this->log('Raw response: ' . substr($result['response_content'], 0, 200) . '...');

        if (!empty($result['tool_calls'])) {
            $this->log('Tool calls made: ' . count($result['tool_calls']));
            foreach ($result['tool_calls'] as $i => $toolCall) {
                $this->log('  Tool ' . ($i + 1) . ': ' . $toolCall['tool_name']);
            }
        } else {
            $this->log('No tool calls were made');
        }
    }
}
