# Prompt Testing Framework

A comprehensive testing framework for real API calls and prompt engineering validation.

## Overview

This framework provides:
- **Real API Testing**: Make actual API calls to test prompts and models
- **MCP Server Testing**: Test MCP server integrations with real endpoints
- **Organized Structure**: Well-organized tests by category and purpose
- **Detailed Reporting**: Comprehensive results with metrics and assertions
- **Easy CLI Interface**: Simple artisan commands for running tests

## Quick Start

### 1. List Available Tests
```bash
php artisan prompt:list
```

### 2. List Available Resources
```bash
php artisan prompt:resources
```

### 3. Run a Test
```bash
# Basic test
php artisan prompt:test McpServer/BasicMcp

# With specific agent and MCP server
php artisan prompt:test McpServer/GoogleDocsTemplate --agent=1 --mcp-server=1

# With verbose output
php artisan prompt:test McpServer/BasicMcp --verbose

# Save results to database
php artisan prompt:test McpServer/BasicMcp --save-results
```

## Test Structure

### Directory Organization
```
app/Services/PromptTesting/
├── Tests/
│   ├── BasePromptTest.php          # Base class for all tests
│   ├── McpServer/
│   │   ├── BasicMcpTest.php        # Basic MCP functionality test
│   │   └── GoogleDocsTemplateTest.php  # Google Docs template test
│   └── [Other Categories]/
├── PromptTestRunner.php            # Main test runner service
└── README.md                       # This file
```

### Test Categories
- **McpServer**: Tests for MCP server integrations
- **Agent**: Tests for agent-specific functionality
- **Workflow**: Tests for workflow and task automation
- **Integration**: End-to-end integration tests

## Writing Tests

### Create a New Test
1. Create a test class extending `BasePromptTest`
2. Implement the `run()` method
3. Use assertion methods to validate results

### Example Test
```php
<?php

namespace App\Services\PromptTesting\Tests\MyCategory;

use App\Services\PromptTesting\Tests\BasePromptTest;

class MyTest extends BasePromptTest
{
    public function run(): array
    {
        // Create thread with prompt
        $thread = $this->createThread('Your prompt here', [
            'name' => 'My Test Thread'
        ]);
        
        // Run the thread
        $result = $this->runThread($thread);
        
        // Perform assertions
        $this->assertNotEmpty($result['response_content'], 'Response should not be empty');
        $this->assertContains('expected text', $result['response_content'], 'Response should contain expected text');
        
        return array_merge($result, $this->getTestResults());
    }
}
```

### Available Assertion Methods
- `assert(bool $condition, string $description, string $errorMessage = '')`: Basic assertion
- `assertContains(string $needle, string $haystack, string $description)`: Check if text contains substring
- `assertNotEmpty(string $value, string $description)`: Check if value is not empty
- `assertToolCalled(array $toolCalls, string $toolName, string $description)`: Check if specific tool was called

## Test Results

### Metrics Included
- **Success/Failure**: Overall test result
- **Duration**: Test execution time
- **Token Usage**: Input/output tokens consumed
- **Cost**: Total API cost for the test
- **Tool Calls**: MCP server tools that were called
- **Assertions**: Individual assertion results

### Example Output
```
=== Test Results ===
✅ Test PASSED

┌─────────────┬──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────┐
│ Metric      │ Value                                                                                                                                                                                                                                    │
├─────────────┼──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
│ Test Name   │ McpServer/BasicMcp                                                                                                                                                                                                                      │
│ Duration    │ 2.347s                                                                                                                                                                                                                                  │
│ Input Tokens│ 1250                                                                                                                                                                                                                                     │
│ Output Tokens│ 420                                                                                                                                                                                                                                      │
│ Total Cost  │ $0.0125                                                                                                                                                                                                                                  │
└─────────────┴──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────┘

=== Assertions ===
✅ Response should not be empty
✅ Response should mention available tools
✅ Input tokens should be greater than 0
✅ Output tokens should be greater than 0

=== Tool Calls ===
Tool Call 1: list_available_tools
Tool Call 2: get_tool_description
```

## Best Practices

### 1. Test Organization
- Group related tests in subdirectories
- Use descriptive test names
- Include meaningful assertions

### 2. Prompt Design
- Make prompts clear and specific
- Include expected behavior descriptions
- Test edge cases and variations

### 3. Assertions
- Test both positive and negative cases
- Check for expected content and structure
- Verify tool calls and API interactions

### 4. Resource Management
- Use appropriate agents for different test types
- Configure MCP servers properly
- Monitor token usage and costs

### 5. Debugging
- Use `--verbose` flag for detailed output
- Save results for later analysis
- Check logs for error details

## Advanced Usage

### Running Multiple Tests
```bash
# Run all tests in a category
for test in $(php artisan prompt:list | grep "McpServer" | awk '{print $2}'); do
    php artisan prompt:test $test --verbose
done
```

### Custom Configuration
Tests can be configured with custom settings:
```php
public function setUp(array $config): void
{
    parent::setUp($config);
    
    // Custom setup for this test
    $this->customSetting = $config['custom_setting'] ?? 'default';
}
```

### Saving Results
Results can be saved to database or log files:
```bash
php artisan prompt:test MyTest --save-results
```

## Troubleshooting

### Common Issues
1. **Test Class Not Found**: Ensure class name matches file name and extends BasePromptTest
2. **Agent Not Found**: Check agent ID with `php artisan prompt:resources`
3. **MCP Server Issues**: Verify MCP server configuration and connectivity
4. **Token Limits**: Monitor token usage to avoid rate limits

### Debug Commands
```bash
# List available resources
php artisan prompt:resources

# Run with verbose output
php artisan prompt:test MyTest --verbose

# Check logs
tail -f storage/logs/laravel.log
```

## Contributing

### Adding New Test Categories
1. Create new subdirectory in `Tests/`
2. Add tests following naming convention
3. Update documentation

### Extending Base Functionality
1. Add new assertion methods to `BasePromptTest`
2. Extend `PromptTestRunner` for new features
3. Update CLI commands as needed

## Examples

See the `Tests/McpServer/` directory for working examples of MCP server testing.