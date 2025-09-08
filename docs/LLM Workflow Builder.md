# Simplified LLM Workflow Builder - Implementation Plan

## 1. Documentation Structure (LLM Knowledge Base)

**Directory**: `docs/workflow-builder-prompts/`

**Core Concept Documents:**
- `workflow-definition.md` - Explains WorkflowDefinition structure, nodes, connections, max_workers
- `task-definition.md` - Details TaskDefinition properties, runner types, artifact modes
- `workflow-connections.md` - How to connect nodes, source/target relationships
- `task-runners-catalog.md` - Available runners (AgentThreadTaskRunner, etc.) with configs
- `agent-selection.md` - How to choose agents based on capabilities
- `prompt-engineering-guide.md` - Best practices for writing task prompts
- `artifact-flow.md` - Understanding input/output artifact modes and levels

**JSON Schema Definitions:**
- `schemas/workflow-definition-schema.json` - Schema for WorkflowDefinition modifications
- `schemas/task-definition-schema.json` - Schema for TaskDefinition creation/updates
- `schemas/workflow-builder-response-schema.json` - Main orchestrator response format
- `schemas/task-builder-response-schema.json` - Individual task builder response

## 2. Database Models & Migrations

### `app/Models/WorkflowBuilder/WorkflowBuilderChat.php`

**Properties:**
- `workflow_input_id` - Links to user's natural language input
- `workflow_definition_id` - The workflow being built/modified (nullable for new workflows)
- `agent_thread_id` - Main planning/communication thread
- `status` - (requirements_gathering, analyzing_plan, building_workflow, evaluating_results, completed, failed)
- `meta` (JSON) - Tracks current phase, build state, artifacts, error recovery info
- `current_workflow_run_id` - Active workflow run being monitored

**Relationships:**
- `belongsTo` WorkflowInput, WorkflowDefinition (nullable), AgentThread, Team, WorkflowRun (current)
- `hasMany` WorkflowRun (build execution history)
- `morphMany` WorkflowInputAssociation (for tracking associated runs)

**Methods:**
- `getCurrentBuildState()` - Returns structured state from meta field
- `updatePhase($phase, $data)` - Updates current phase and meta
- `attachArtifacts($artifacts)` - Adds artifacts to meta and broadcasts event
- `addThreadMessage($message)` - Adds message and broadcasts event
- `isWaitingForWorkflow()` - Checks if waiting for workflow run completion
- `getLatestArtifacts()` - Returns most recent workflow build artifacts

**Events Fired:**
- `WorkflowBuilderChatUpdatedEvent` - When artifacts added or messages updated

### Migration: `create_workflow_builder_chats_table.php`
Creates single table with proper indexes and foreign keys

## 3. Services Layer

### `app/Services/WorkflowBuilder/WorkflowBuilderService.php`
**Main Orchestrator Service**

**Dependencies:**
- WorkflowRunnerService, AgentThreadService
- WorkflowBuilderDocumentationService

**Key Methods:**

`startRequirementsGathering(string $prompt, ?int $workflowDefinitionId = null, ?int $chatId = null)`
- Creates/retrieves WorkflowBuilderChat record
- Creates AgentThread for planning conversation
- Initiates planning phase with LLM using AgentThreadService
- Returns WorkflowBuilderChat instance

`generateWorkflowPlan(WorkflowBuilderChat $chat, string $userInput)`
- Uses AgentThreadService with planning context
- Loads existing workflow state if modifying
- Returns high-level workflow plan for user approval
- Updates chat meta with plan state

`startWorkflowBuild(WorkflowBuilderChat $chat)`
- Prepares artifacts (user input + approved plan + current workflow state)
- Calls WorkflowRunnerService::start() with builder workflow definition
- Associates WorkflowRun with chat for tracking
- Updates chat status to 'building_workflow'

`processWorkflowCompletion(WorkflowBuilderChat $chat, WorkflowRun $completedRun)`
- Extracts build artifacts from completed workflow
- Applies changes to WorkflowDefinition/TaskDefinitions
- Updates chat with artifacts and status
- Triggers evaluation step

`evaluateAndCommunicateResults(WorkflowBuilderChat $chat)`
- Creates new AgentThread for result evaluation
- Uses AgentThreadService to analyze build artifacts
- Generates user-friendly summary and recommendations
- Updates chat with final results and completes process

### `app/Services/WorkflowBuilder/WorkflowBuilderDocumentationService.php`
**Documentation Loading and Context Building**

**Methods:**

`getPlanningContext(WorkflowDefinition $workflow = null)`
- Loads high-level workflow concepts for planning
- Includes current workflow structure if modifying existing
- Returns formatted prompt context for requirements gathering

`getOrchestratorContext(WorkflowDefinition $workflow = null)`
- Loads workflow-definition.md, task-definition.md, connections.md
- Includes current workflow structure if exists
- Returns formatted prompt context for workflow building

`getTaskBuilderContext(array $specification, WorkflowDefinition $workflow)`
- Loads task-specific docs (runners, agents, prompts)
- Includes related task definitions for context
- Adds artifact flow documentation
- Returns specialized prompt for task building

`getEvaluationContext(array $artifacts)`
- Loads evaluation and communication guidelines
- Formats build artifacts for analysis
- Returns context for result evaluation

`loadDocumentFile($filename)`
- Reads from docs/workflow-builder-prompts/
- Caches in memory for performance
- Returns markdown content

## 4. Task Runners

### `app/Services/Task/Runners/WorkflowDefinitionBuilderTaskRunner.php`
**Extends BaseTaskRunner**

**Key Methods:**

`prepareProcess()`
- Names process "Workflow Organization Analysis"
- Timeout configured in workflow/task definition (not hardcoded)

`run()`
- Loads orchestrator context via WorkflowBuilderDocumentationService
- Builds comprehensive prompt with current workflow state
- Runs AgentThreadTaskRunner with organization schema
- Outputs artifacts per task definition change (using split mode)

`buildOrchestratorPrompt($input, $currentWorkflow)`
- Constructs detailed prompt with user intent
- Includes current workflow structure
- Adds examples and constraints
- Returns formatted prompt string

### `app/Services/Task/Runners/TaskDefinitionBuilderTaskRunner.php`
**Extends BaseTaskRunner**

**Config:**
- `input_artifact_mode = 'split'` (processes artifacts individually)

**Methods:**

`run()`
- Receives single task specification artifact
- Loads task-specific documentation context
- Determines if create/update/delete action
- Runs AgentThread with task builder schema
- Outputs completed task definition

`buildTaskPrompt($specification, $context)`
- Creates focused prompt for single task
- Includes workflow context and connections
- Adds runner-specific documentation
- Returns specialized prompt

`applyTaskDefinition($specification, $result)`
- Creates or updates TaskDefinition model
- Sets all properties from LLM response
- Handles TaskDefinitionDirectives creation
- Updates WorkflowNode if needed

## 5. Artisan Command Interface

### `app/Console/Commands/WorkflowBuilderCommand.php`
**Interactive Chat-Style Command**

**Usage:**
```bash
# Start new workflow build
php artisan workflow:build "Create a content analysis workflow"

# Continue existing chat
php artisan workflow:build --chat=123

# Modify existing workflow
php artisan workflow:build "Add validation step" --workflow=456
```

**Key Methods:**

`handle()`
- Parses command arguments (prompt, chat ID, workflow ID)
- Starts or continues WorkflowBuilderChat session
- Manages chat loop for user interaction
- Displays progress and results

`startNewChat($prompt, $workflowId = null)`
- Creates new WorkflowBuilderChat
- Initiates requirements gathering phase
- Returns to chat loop

`continueChat($chatId)`
- Loads existing WorkflowBuilderChat
- Resumes from current phase
- Handles error recovery if needed

`displayPlanAndAwaitApproval($chat, $plan)`
- Shows generated workflow plan
- Prompts for user approval/corrections
- Handles user feedback loop

`monitorWorkflowProgress($chat)`
- Displays workflow build progress
- Listens for completion events
- Shows real-time status updates

`displayResults($chat, $results)`
- Shows final build results
- Explains changes made
- Prompts for next instruction

## 6. Hard-Coded Workflow Definition

**Location**: Database Seeder or Migration
**Creates**: "LLM Workflow Builder" WorkflowDefinition

**Structure:**
```
Node 1: WorkflowInput (WorkflowInputTaskRunner)
  ↓
Node 2: Workflow Orchestrator (WorkflowDefinitionBuilderTaskRunner)
  - Output artifact mode: "split by task"
  - Timeout: 120s (configured in task definition)
  ↓
Node 3: Task Definition Builder (TaskDefinitionBuilderTaskRunner)
  - Input artifact mode: "split" (parallel processing)
  - Output: Individual task definitions
  ↓
Node 4: WorkflowOutput (WorkflowOutputTaskRunner)
```

**Note**: This workflow only handles the building phase. Planning and evaluation are handled separately via direct AgentThreadService calls.

## 7. Complete Integration Flow

### Phase 1: Requirements Gathering
1. User runs artisan command with prompt
2. WorkflowBuilderService::startRequirementsGathering() called
3. Creates WorkflowBuilderChat and AgentThread
4. Uses AgentThreadService for planning conversation
5. LLM generates workflow plan based on requirements
6. Plan displayed to user for approval/correction
7. User feedback loop until plan approved

### Phase 2: Workflow Building
1. WorkflowBuilderService::startWorkflowBuild() called
2. Creates WorkflowInput with approved plan
3. Starts hard-coded builder workflow via WorkflowRunnerService
4. WorkflowDefinitionBuilderTaskRunner analyzes and outputs task specifications
5. TaskDefinitionBuilderTaskRunner processes each task in parallel
6. Workflow completes with task definition artifacts

### Phase 3: Event-Driven Completion
1. WorkflowRunUpdatedEvent fired when workflow completes
2. WorkflowListenerCompletedListener catches event
3. Routes to WorkflowBuilderService::processWorkflowCompletion()
4. Extracts artifacts and applies changes to database
5. Updates WorkflowBuilderChat status and broadcasts update

### Phase 4: Result Evaluation
1. WorkflowBuilderService::evaluateAndCommunicateResults() triggered
2. Creates new AgentThread for evaluation
3. Uses AgentThreadService to analyze build artifacts
4. Generates user-friendly summary and recommendations
5. Updates chat and broadcasts final results

### Phase 5: User Interaction
1. Artisan command receives broadcast events
2. Displays results to user
3. Prompts for next instruction
4. Returns to Phase 1 with new requirements

### Error Recovery:
1. Failed workflows identified in chat meta
2. Context preserved for manual recovery
3. Artisan command can resume from failure point
4. User notified of specific issues and recovery options

## 8. Event System & Broadcasting

### `app/Events/WorkflowBuilderChatUpdatedEvent.php`
**Fired when:**
- New artifacts added to chat
- New messages added to associated AgentThread
- Chat phase/status changes

**Payload:**
- WorkflowBuilderChat instance
- Update type (artifacts, messages, status)
- New data

### `app/Listeners/WorkflowBuilder/WorkflowBuilderCompletedListener.php`
**Handles WorkflowRunUpdatedEvent for builder workflows**

**Methods:**
`handle(WorkflowRunUpdatedEvent $event)`
- Identifies workflow builder runs
- Calls WorkflowBuilderService::processWorkflowCompletion()
- Triggers result evaluation phase

## 9. Key Integration Points

**With Existing WorkflowRunnerService:**
- Uses standard workflow execution for builder workflow only
- Leverages existing artifact management
- Integrates with task process tracking

**With AgentThreadService:**
- Direct integration for planning and evaluation phases
- Uses existing response format handling
- Leverages JSON schema validation
- No custom wrappers or additional complexity

**With TaskRunnerService:**
- Custom runners follow established patterns
- Uses existing artifact flow mechanisms
- Integrates with process execution system

**With Event System:**
- Listens for WorkflowRun completion events
- Broadcasts WorkflowBuilderChat updates
- Enables real-time UI and command line updates

**With Team Scoping:**
- All models include team_id
- Inherits access control from base system
- Scoped queries automatic via repositories

## Summary
This simplified architecture removes unnecessary complexity while maintaining all required functionality. The three-phase approach (requirements gathering → workflow building → result evaluation) provides clear separation of concerns, and the event-driven completion enables real-time interaction via both artisan command and potential UI implementations.
