# LLM Workflow Builder Setup & Usage Guide

## Overview

The LLM Workflow Builder is now fully implemented and ready for use. This system allows users to create and modify complex workflow definitions through natural language conversations with AI agents.

## Setup Instructions

### 1. Run Migrations & Seeders

```bash
# Start Docker/Sail environment
./vendor/bin/sail up -d

# Run migrations to create database tables
./vendor/bin/sail artisan migrate

# Run seeders to create test data and workflow builder components
./vendor/bin/sail db:seed
```

### 2. Verify Setup

Check that the system is properly set up:

```bash
# Verify the LLM Workflow Builder workflow exists
./vendor/bin/sail artisan workflow:list | grep "LLM Workflow Builder"

# Verify required agents exist
./vendor/bin/sail artisan tinker
# In tinker: App\Models\Agent\Agent::where('name', 'Workflow Planner')->exists()
# In tinker: App\Models\Agent\Agent::where('name', 'Workflow Evaluator')->exists()
```

## Usage Examples

### Basic Workflow Creation

```bash
# Create a new workflow from scratch
./vendor/bin/sail artisan workflow:build "Create a content analysis workflow that extracts key insights from documents"

# Create a data processing workflow
./vendor/bin/sail artisan workflow:build "Build a data processing pipeline that validates, transforms, and stores user data"
```

### Continuing Existing Sessions

```bash
# Continue a previous chat session
./vendor/bin/sail artisan workflow:build --chat=123

# Modify an existing workflow
./vendor/bin/sail artisan workflow:build "Add a validation step" --workflow=456
```

### Specifying Team Context

```bash
# Use a specific team
./vendor/bin/sail artisan workflow:build "Create a workflow" --team=team-uuid-here
```

## System Architecture

### Key Components Created:

1. **Database Models**:
   - `WorkflowBuilderChat` - Manages chat sessions and state
   - Migration for workflow_builder_chats table

2. **Services**:
   - `WorkflowBuilderService` - Main orchestration service
   - `WorkflowBuilderDocumentationService` - Loads LLM context

3. **Task Runners**:
   - `WorkflowDefinitionBuilderTaskRunner` - Analyzes requirements and creates task specs
   - `TaskDefinitionBuilderTaskRunner` - Creates individual task definitions

4. **Command Interface**:
   - `WorkflowBuilderCommand` - Interactive chat-style CLI

5. **Event System**:
   - `WorkflowBuilderChatUpdatedEvent` - Broadcasts updates
   - `WorkflowBuilderCompletedListener` - Handles workflow completion

6. **Documentation**:
   - Complete documentation set in `docs/workflow-builder-prompts/`
   - JSON schemas for LLM response validation

### Workflow Process:

1. **Requirements Gathering**: AI engages with user to understand needs
2. **Plan Generation**: AI creates high-level workflow plan for approval  
3. **Workflow Building**: Automated creation via specialized task runners
4. **Result Evaluation**: AI analyzes and explains what was built

## Testing

### Run Tests

```bash
# Run the comprehensive integration test
./vendor/bin/sail artisan test --filter=WorkflowBuilderIntegrationTest

# Run all workflow builder related tests
./vendor/bin/sail artisan test tests/Unit/Models/WorkflowBuilderChatTest.php
./vendor/bin/sail artisan test tests/Unit/Services/WorkflowBuilder/
```

### Manual Testing

```bash
# Test the full flow
./vendor/bin/sail artisan workflow:build "Create a simple task workflow with input processing and output generation"

# Follow the prompts:
# 1. Review the generated plan
# 2. Approve or request modifications
# 3. Monitor the build progress
# 4. Review the final results
```

## Troubleshooting

### Common Issues

1. **No teams available**: Run `./vendor/bin/sail db:seed` to create test data
2. **Missing agents**: The WorkflowBuilderSeeder creates required agents automatically
3. **Redis connection errors**: Ensure Docker/Sail is running with Redis service
4. **Migration errors**: Check that all migrations run successfully

### Debug Mode

```bash
# Run command with verbose output
./vendor/bin/sail artisan workflow:build "test" --verbose

# Check logs
./vendor/bin/sail logs laravel.test

# Monitor database changes
./vendor/bin/sail artisan tinker
# Monitor WorkflowBuilderChat records and status changes
```

## Key Features Implemented

✅ **Natural Language Processing**: Parses both JSON and text responses from LLMs  
✅ **Real-time Progress**: Event-driven progress monitoring  
✅ **Workflow Application**: Creates actual WorkflowDefinition and TaskDefinition records  
✅ **Agent Management**: Automatically assigns appropriate agents to tasks  
✅ **Error Recovery**: Handles failures and allows resuming sessions  
✅ **Team Scoping**: All operations scoped to teams automatically  
✅ **Comprehensive Testing**: Full integration test coverage  

## Next Steps

1. **Production Deployment**: System is ready for production use
2. **UI Integration**: Could be integrated with a web interface
3. **Enhanced Agents**: Create specialized agents for different workflow types
4. **Advanced Features**: Add workflow templates, sharing, versioning

The LLM Workflow Builder is now a fully functional system that bridges natural language requirements with technical workflow implementations.