<?php

namespace App\Services\PromptTesting\Tests\McpServer;

use App\Services\PromptTesting\Tests\BasePromptTest;

class GoogleDocsTemplateTest extends BasePromptTest
{
    public function run(): array
    {
        // Test the Google Docs template functionality with MCP server
        $prompt = $this->getPrompt();

        // Create and run the thread
        $thread = $this->createThread($prompt, [
            'name' => 'Google Docs Template Test',
        ]);

        $result = $this->runThread($thread);

        // Perform assertions
        $this->runAssertions($result);

        return array_merge($result, $this->getTestResults());
    }

    private function getPrompt(): string
    {
        return <<<'PROMPT'
Export this treatment summary using the google doc template tool.

https://docs.google.com/document/d/1UzdN0ltymmcSjD964cOxjz8RfyuCakC9z3Y70Dftxoc/edit?tab=t.0

That is the templated file. Go create a new file filling in all the template fields with the correct values based on the data you are given.

FOR now there is no data - just make some stuff up for a personal injury demand letter.
PROMPT;
    }

    private function runAssertions(array $result): void
    {
        // Debug logging
        $this->log('Thread run status: ' . ($result['thread_run']->status ?? 'unknown'));
        $this->log('Response content length: ' . strlen($result['response_content'] ?? ''));

        if (empty($result['response_content'])) {
            $this->log('No response content - checking thread run details');
            if (isset($result['thread_run'])) {
                $this->log('Thread run failed_at: ' . $result['thread_run']->failed_at);
                $this->log('Thread run completed_at: ' . $result['thread_run']->completed_at);
            }
        }

        // Assert that we got a response
        $this->assertNotEmpty(
            $result['response_content'],
            'Response should not be empty'
        );

        // Assert that MCP tools were called
        $this->assert(
            !empty($result['tool_calls']),
            'MCP tools should be called',
            'No tool calls found in response'
        );

        // Assert that Google Docs tool was specifically called
        $this->assertToolCalled(
            $result['tool_calls'],
            'google_docs_create_document_from_template',
            'Google Docs create document from template tool should be called'
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

        // Check that the response mentions document creation
        $this->assertContains(
            'document',
            strtolower($result['response_content']),
            'Response should mention document creation'
        );

        // Check that the response references the template
        $this->assertContains(
            'template',
            strtolower($result['response_content']),
            'Response should reference template usage'
        );
    }
}
