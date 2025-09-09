# TaskDefinition Structure Guide

## Overview

TaskDefinition is the core model that defines individual tasks within workflows. Each task represents a specific processing step with its own configuration, prompt, agent assignment, and execution parameters.

## Core Properties

### Basic Information
- **name**: Unique identifier for the task (max 80 chars, unique per team)
- **description**: Detailed explanation of the task's purpose and functionality
- **prompt**: The instruction text that guides the task execution
- **team_id**: Automatically scoped to the current team

### Task Runner Configuration
- **task_runner_name**: Specifies which runner class executes this task
- **task_runner_config**: JSON configuration specific to the runner type
- **timeout_after_seconds**: Maximum execution time before task fails

### AI Integration
- **agent_id**: References the AI agent that processes this task
- **response_format**: Specifies output format ('json_schema' or text)
- **schema_definition_id**: JSON schema for structured responses

### Artifact Flow Control
- **input_artifact_mode**: How input data is processed
  - `null` (default): Process all artifacts together
  - `"split"`: Process each artifact separately in parallel
- **input_artifact_levels**: JSON array specifying which artifact levels to include
- **output_artifact_mode**: How output is generated
  - `""` (standard): One output per task execution
  - `"Per Process"`: One output per parallel process
  - `"Group All"`: Single consolidated output
- **output_artifact_levels**: JSON array specifying output artifact structure

### Queue Management
- **task_queue_type_id**: Specifies which queue system handles this task

## Task Runner Types

### AgentThreadTaskRunner ("AI Agent")
The primary runner for AI-powered task execution.

**Key Features:**
- Integrates with AgentThreadService for AI communication
- Supports both text and JSON schema responses
- Handles context management and conversation flow
- Supports MCP server integration

**Configuration Options:**
```json
{
  "timeout": 120,
  "include_text_sources": true,
  "deduplicate_names": false,
  "mcp_server_id": "optional-mcp-server-id"
}
```

### WorkflowInputTaskRunner
Entry point runner that captures initial workflow input.

**Use Cases:**
- Starting nodes in workflows
- Data ingestion points
- User input capture

### WorkflowOutputTaskRunner
Final stage runner that formats and presents workflow results.

**Use Cases:**
- Final output formatting
- Result aggregation
- Data export preparation

### Data Processing Runners
- **LoadFromDatabaseTaskRunner**: Retrieve data from database
- **SaveToDatabaseTaskRunner**: Store results to database  
- **LoadCsvTaskRunner**: Import CSV data
- **FilterArtifactsTaskRunner**: Filter and transform artifacts
- **SplitArtifactsTaskRunner**: Split artifacts into components
- **MergeArtifactsTaskRunner**: Combine multiple artifacts

### Specialized Processing Runners
- **CategorizeArtifactsTaskRunner**: Classify and organize data
- **ClassifierTaskRunner**: Advanced classification logic
- **ImageToTextTranscoderTaskRunner**: OCR and image analysis
- **PageOrganizerTaskRunner**: Document structure organization

## Artifact Modes Explained

### Input Artifact Modes

**Standard Processing (null):**
All input artifacts are processed together in a single task execution.
- Use when: Task needs to analyze all data together
- Example: Comparing multiple documents for similarities

**Split Processing ("split"):**
Each input artifact is processed separately in parallel.
- Use when: Task can process items independently
- Example: Analyzing individual documents for key insights
- Enables high parallelism and faster processing

### Output Artifact Modes

**Standard (""):**
Single output artifact per task execution.
- Most common mode for straightforward processing

**Per Process ("Per Process"):**
Separate output artifact for each parallel process.
- Use with split input mode for 1:1 input/output mapping
- Maintains traceability between input and output

**Group All ("Group All"):**
Single consolidated output combining all process results.
- Use when final result needs all data merged
- Example: Generating summary report from multiple analyses

## Agent Selection Criteria

### Capability-Based Selection
Choose agents based on:
- **Model capabilities**: GPT-4 for complex reasoning, GPT-3.5 for simpler tasks
- **Context window**: Larger windows for document analysis
- **Specialized skills**: Code generation, analysis, creative writing
- **Cost considerations**: Balance performance vs. cost requirements

### Response Format Compatibility
- **Text responses**: Any agent can handle text output
- **JSON schema responses**: Ensure agent supports structured output
- **Complex schemas**: Use more capable models for intricate data structures

## Prompt Engineering Best Practices

### Prompt Structure
1. **Clear objective**: State exactly what the task should accomplish
2. **Context information**: Provide relevant background and constraints
3. **Input description**: Explain the format and content of input data
4. **Output requirements**: Specify exact format and structure expected
5. **Examples**: Include sample inputs and outputs when helpful

### Effective Prompt Patterns
- Use active voice and specific verbs
- Include validation criteria for output quality
- Specify handling of edge cases and errors
- Provide clear success metrics

### Schema Integration
For JSON responses:
- Reference the schema definition clearly in the prompt
- Explain any complex schema relationships
- Provide examples of valid JSON output
- Specify required vs. optional fields

## TaskDefinitionDirectives

### Purpose
Directives provide additional instructions that are added to the agent thread.

### Types
- **Before Thread (SECTION_TOP)**: Instructions added before the main prompt
- **After Thread (SECTION_BOTTOM)**: Instructions added after the main prompt

### Use Cases
- System-level instructions that apply to all tasks of this type
- Context setup that doesn't belong in the main prompt
- Post-processing instructions for output validation

## Performance Considerations

### Timeout Configuration
- Set realistic timeouts based on task complexity
- Consider agent model speed and response times
- Account for potential network latency
- Balance between allowing completion and preventing hangs

### Parallel Processing
When using split input mode:
- Consider system resource limits
- Monitor memory usage for large datasets
- Plan for optimal chunk sizes
- Account for downstream processing capacity

### Cost Optimization
- Choose appropriate agent models for task complexity
- Optimize prompt length to reduce token usage
- Use caching strategies for repeated operations
- Monitor usage patterns and adjust configurations

## Integration with WorkflowNodes

### Node-Task Relationship
- Each WorkflowNode references exactly one TaskDefinition
- Multiple nodes can share the same TaskDefinition
- Node-specific parameters override task defaults

### Connection Requirements
- Input/output artifact modes must be compatible with connections
- Consider data flow requirements when setting artifact levels
- Ensure schema compatibility between connected tasks

## Common Task Patterns

### Analysis Tasks
```json
{
  "input_artifact_mode": "split",
  "output_artifact_mode": "Per Process",
  "response_format": "json_schema"
}
```

### Aggregation Tasks
```json
{
  "input_artifact_mode": null,
  "output_artifact_mode": "Group All",
  "response_format": "text"
}
```

### Transformation Tasks
```json
{
  "input_artifact_mode": "split",
  "output_artifact_mode": "Per Process",
  "response_format": "json_schema"
}
```

This structure enables flexible, powerful task definition that can handle a wide variety of processing requirements while maintaining clean separation of concerns and optimal performance.