# Extract Data Task Runner Guide

This guide explains the Extract Data task runner in GPT Manager, including the multi-phase extraction pipeline, classification, identity resolution, and hierarchical data extraction.

## Overview

The Extract Data task runner extracts structured data from documents (PDFs, images, text) into TeamObjects. It uses a sophisticated multi-phase pipeline:

1. **Planning** - LLM analyzes schema to determine extraction strategy
2. **Transcoding** - OCR and LLM processing for images/PDFs
3. **Classification** - Per-page classification to filter relevant content
4. **Identity Extraction** - Extract identity fields and resolve/create TeamObjects
5. **Remaining Extraction** - Extract additional fields for each resolved object
6. **Rollup** - Compile all extracted data into structured JSON output

The system supports hierarchical schemas with parent-child relationships, processes documents level-by-level to maintain referential integrity, and uses intelligent search strategies to balance speed with thoroughness.

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           Frontend (Vue SPA)                                 │
├─────────────────────────────────────────────────────────────────────────────┤
│  ExtractDataTaskRunnerConfig  - Configuration UI for extract data tasks      │
│  SchemaEditorToolbox          - Schema selection and editing                 │
│  TaskRunCard                  - Task run monitoring                          │
│  TaskProcessCard              - Individual process monitoring                │
│  ArtifactCard                 - Extraction result display                    │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           Backend (Laravel)                                  │
├─────────────────────────────────────────────────────────────────────────────┤
│  Task Runner:                                                                │
│    ExtractDataTaskRunner        - Main task runner with operation routing    │
│    AgentThreadTaskRunner        - Base class for LLM agent threads           │
│                                                                              │
│  Orchestration:                                                              │
│    ExtractionStateOrchestrator  - Unified state machine for phase control    │
│    ExtractionProcessOrchestrator- Level-by-level extraction management       │
│                                                                              │
│  Planning:                                                                   │
│    PerObjectPlanningService     - Per-object identity/remaining planning     │
│    ExtractionPlanningService    - Plan caching and retrieval                 │
│    PlanningPhaseService         - Planning phase state transitions           │
│                                                                              │
│  Classification:                                                             │
│    ClassificationOrchestrator   - Classification phase management            │
│    ClassificationExecutorService- Page classification execution              │
│                                                                              │
│  Extraction:                                                                 │
│    IdentityExtractionService    - Identity field extraction + TeamObject     │
│    RemainingExtractionService   - Additional field extraction                │
│    GroupExtractionService       - Extraction group execution                 │
│    DuplicateRecordResolver      - Duplicate detection and resolution         │
│                                                                              │
│  Support:                                                                    │
│    ArtifactPreparationService   - Create extraction artifacts                │
│    TranscodePrerequisiteService - OCR and LLM transcoding                    │
│    ExtractionRollupService      - Final JSON rollup                          │
│    ExtractionArtifactBuilder    - Build output artifacts                     │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Operations / Phases

ExtractDataTaskRunner uses operation-based routing to handle different phases:

| Operation | Description |
|-----------|-------------|
| `(default)` | Initialize - Delegates to orchestrator to determine first phase |
| `Transcode` | Convert images/PDFs to text using OCR + LLM |
| `Plan: Identify` | LLM selects identity fields for an object type |
| `Plan: Remaining` | LLM groups remaining fields into extraction groups |
| `Classify` | Classify a single page using boolean schema |
| `Extract Identity` | Extract identity fields and resolve TeamObjects |
| `Extract Remaining` | Extract additional fields for resolved objects |

### Phase Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              INITIALIZATION                                  │
│                                    │                                         │
│                     Has cached plan? ─────── Yes ──────┐                    │
│                           │ No                         │                    │
│                           ▼                            │                    │
│  ┌─────────────────────────────────────────────────┐   │                    │
│  │              PLANNING PHASE                      │   │                    │
│  │  1. Plan: Identify (per object type)            │   │                    │
│  │  2. Plan: Remaining (per object type)           │   │                    │
│  │  3. Compile final plan                          │   │                    │
│  │  4. Cache plan for future runs                  │   │                    │
│  └─────────────────────────────────────────────────┘   │                    │
│                           │                            │                    │
│                           ▼◄───────────────────────────┘                    │
│  ┌─────────────────────────────────────────────────┐                        │
│  │            ARTIFACT PREPARATION                  │                        │
│  │  1. Create parent output artifact               │                        │
│  │  2. Create child artifacts (one per page)       │                        │
│  │  3. Build classification schema                 │                        │
│  └─────────────────────────────────────────────────┘                        │
│                           │                                                  │
│              Files need transcoding? ─── No ───────┐                        │
│                           │ Yes                    │                        │
│                           ▼                        │                        │
│  ┌─────────────────────────────────────────────────┐   │                    │
│  │              TRANSCODING PHASE                   │   │                    │
│  │  • Create transcode process per artifact        │   │                    │
│  │  • Run OCR + LLM transcode (parallel)           │   │                    │
│  │  • Store transcodes as StoredFile relationships │   │                    │
│  └─────────────────────────────────────────────────┘   │                    │
│                           │                            │                    │
│                           ▼◄───────────────────────────┘                    │
│  ┌─────────────────────────────────────────────────┐                        │
│  │            CLASSIFICATION PHASE                  │                        │
│  │  • Create classify process per page             │                        │
│  │  • LLM classifies using boolean schema          │                        │
│  │  • Cache results in artifact & StoredFile meta  │                        │
│  └─────────────────────────────────────────────────┘                        │
│                           │                                                  │
│                           ▼                                                  │
│  ┌─────────────────────────────────────────────────┐                        │
│  │         LEVEL-BY-LEVEL EXTRACTION                │                        │
│  │  For each level (0 to N):                       │                        │
│  │    ┌───────────────────────────────────────┐    │                        │
│  │    │  IDENTITY EXTRACTION                  │    │                        │
│  │    │  • Filter pages by classification     │    │                        │
│  │    │  • Extract identity fields (LLM)      │    │                        │
│  │    │  • Generate search queries            │    │                        │
│  │    │  • Resolve duplicates (DB + LLM)      │    │                        │
│  │    │  • Create/update TeamObjects          │    │                        │
│  │    └───────────────────────────────────────┘    │                        │
│  │                        │                        │                        │
│  │                        ▼                        │                        │
│  │    ┌───────────────────────────────────────┐    │                        │
│  │    │  REMAINING EXTRACTION                 │    │                        │
│  │    │  • One process per object per group   │    │                        │
│  │    │  • Extract remaining fields (LLM)     │    │                        │
│  │    │  • Update TeamObjects with data       │    │                        │
│  │    └───────────────────────────────────────┘    │                        │
│  │                        │                        │                        │
│  │            Advance to next level                │                        │
│  └─────────────────────────────────────────────────┘                        │
│                           │                                                  │
│                           ▼                                                  │
│  ┌─────────────────────────────────────────────────┐                        │
│  │                  ROLLUP PHASE                    │                        │
│  │  • Collect all extraction artifacts             │                        │
│  │  • Build hierarchical JSON structure            │                        │
│  │  • Store in parent artifact json_content        │                        │
│  └─────────────────────────────────────────────────┘                        │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Data Models

### TaskRun Meta Structure

The TaskRun stores extraction state in its `meta` field:

```php
[
    // Compiled extraction plan (from planning phase)
    'extraction_plan' => [
        'levels' => [
            [
                'object_type' => 'Demand',
                'identity_groups' => [...],
                'remaining_groups' => [...],
            ],
            [
                'object_type' => 'Provider',
                'parent_type' => 'Demand',
                'identity_groups' => [...],
                'remaining_groups' => [...],
            ],
        ],
    ],

    // Boolean classification schema (generated from plan)
    'classification_schema' => [
        'demand_identification' => 'Does this page contain demand identification info?',
        'provider_identification' => 'Does this page contain provider info?',
        // ... one entry per extraction category
    ],

    // Current extraction level (0-indexed)
    'current_level' => 0,

    // Per-level completion tracking
    'level_progress' => [
        0 => ['identity_complete' => true, 'extraction_complete' => true],
        1 => ['identity_complete' => false, 'extraction_complete' => false],
    ],

    // Resolved TeamObject IDs by type and level
    'resolved_objects' => [
        'Demand' => [0 => [1, 2, 3]],      // Level 0: IDs 1, 2, 3
        'Provider' => [1 => [10, 11, 12]], // Level 1: IDs 10, 11, 12
    ],

    // Per-object planning results (before compilation)
    'per_object_plans' => [
        'Demand' => ['identity_fields' => [...], 'remaining_groups' => [...]],
        'Provider' => ['identity_fields' => [...], 'remaining_groups' => [...]],
    ],
]
```

### TaskProcess Meta Structure

#### Identity Extraction Process

```php
[
    'level' => 0,
    'identity_group' => [
        'object_type' => 'Demand',
        'fields' => ['name', 'reference_number'],
        'fragment_selector' => [...],
        'search_mode' => 'skim',
    ],
    'parent_object_ids' => [1, 2],  // Possible parent IDs (for child levels)
    'search_mode' => 'skim',         // Resolved search mode
]
```

#### Remaining Extraction Process

```php
[
    'level' => 0,
    'extraction_group' => [
        'name' => 'demographics',
        'fields' => ['date_of_birth', 'gender', 'address'],
        'search_mode' => 'exhaustive',
    ],
    'object_id' => 1,       // TeamObject to update
    'search_mode' => 'exhaustive',
]
```

### Artifact Meta Structure

#### Classification Result

```php
[
    'classification' => [
        'demand_identification' => true,
        'provider_identification' => false,
        'diagnosis_codes' => true,
    ],
]
```

#### Extraction Result

```php
[
    'operation' => 'Extract Identity',
    'parent_id' => 1,              // Parent TeamObject ID
    'relationship_key' => 'providers',  // Schema property name
    'is_array_type' => true,       // Schema cardinality
]
```

## Backend Services

### ExtractionStateOrchestrator

**Location:** `app/Services/Task/DataExtraction/ExtractionStateOrchestrator.php`

The unified state machine that controls all phase transitions. Called from both initialization and after process completion.

**Key Method:** `advanceToNextPhase(TaskRun $taskRun, ?TaskProcess $taskProcess = null)`

Checks conditions in order:
1. `needsPlanning()` → Create planning processes
2. `needsExtractionArtifacts()` → Create artifact structure
3. `needsTranscoding()` → Create transcode processes
4. `needsClassification()` → Create classification processes
5. `needsIdentityExtraction()` → Create identity processes for current level
6. `needsRemainingExtraction()` → Create remaining processes for current level
7. `canAdvanceLevel()` → Advance to next level and repeat
8. All complete → Run rollup

### IdentityExtractionService

**Location:** `app/Services/Task/DataExtraction/IdentityExtractionService.php`

Orchestrates the complete identity extraction workflow:

1. **Resolve Parent Context** - Get parent TeamObject(s) for child levels
2. **Extract Identity Fields** - LLM extracts identity fields + search queries
3. **Resolve Duplicates** - Progressive database search + LLM comparison
4. **Create/Update TeamObject** - Create new or update existing
5. **Build Output Artifacts** - Store extraction results

**Search Modes:**
- **Skim Mode** - Batched extraction with confidence tracking, stops when confident
- **Exhaustive Mode** - Processes all artifacts in single request

### ClassificationExecutorService

**Location:** `app/Services/Task/DataExtraction/ClassificationExecutorService.php`

Classifies pages using a boolean schema generated from the extraction plan.

**Caching:** Results are cached in `StoredFile.meta['classifications'][schema_hash]` to avoid re-classification on subsequent runs.

### DuplicateRecordResolver

**Location:** `app/Services/Task/DataExtraction/DuplicateRecordResolver.php`

Handles duplicate detection with progressive search:
1. Generate 3+ search queries from specific to broad
2. Search database with each query until candidates found
3. If exact match found → return immediately
4. If candidates found → LLM compares to determine best match
5. If no match → create new record

### ExtractionRollupService

**Location:** `app/Services/Task/DataExtraction/ExtractionRollupService.php`

Compiles all extracted data into structured JSON format.

**Output Format:**
```json
{
  "extracted_at": "2024-01-15T10:30:00Z",
  "objects": [
    {
      "id": 1,
      "type": "Demand",
      "name": "Demand #12345",
      "reference_number": "12345",
      "providers": [
        {
          "id": 10,
          "type": "Provider",
          "name": "Dr. Smith",
          "npi": "1234567890"
        }
      ]
    }
  ],
  "summary": {
    "total_objects": 5,
    "by_type": {
      "Demand": 2,
      "Provider": 3
    }
  }
}
```

## Frontend Components

### ExtractDataTaskRunnerConfig

**Location:** `spa/src/components/Modules/TaskDefinitions/TaskRunners/Configs/ExtractDataTaskRunnerConfig.vue`

The main configuration UI with these sections:

#### Search Strategy
- **Global Search Mode** - Intelligent / Skim Only / Exhaustive Only
- **Confidence Threshold** (1-5) - When to stop in skim mode

#### Grouping Configuration
- **Maximum Data Points Per Group** (1-50) - Controls extraction group size
- **User Planning Hints** - Markdown guidance for the planning LLM

#### Context Configuration
- **Enable Context Pages** - Include adjacent pages for context
- **Adjacency Threshold** (1-5) - How close pages must be to group
- **Context Pages Before/After** (0-10) - Pages to include

#### Extraction Instructions
- **Instructions** - Markdown instructions for the extraction LLM

#### Output Schema
- Uses `SchemaEditorToolbox` for schema selection
- Forces JSON schema response format
- Maximum 1 fragment allowed

### Task Monitoring Components

#### TaskRunCard

**Location:** `spa/src/components/Modules/TaskDefinitions/Panels/TaskRunCard.vue`

Displays task run status:
- Process count with expand/collapse
- Status timer pill
- AI token usage
- Resume/stop/delete actions

#### TaskProcessCard

**Location:** `spa/src/components/Modules/TaskDefinitions/Panels/TaskProcessCard.vue`

Detailed process monitoring:
- Operation name and status
- Progress bar with percentage
- Expandable sections:
  - **Agent Thread** - LLM conversation messages
  - **Job Dispatches** - API calls and logs
  - **Input Artifacts** - Input data
  - **Output Artifacts** - Extraction results

#### ArtifactCard

**Location:** `spa/src/components/Modules/Artifacts/ArtifactCard.vue`

Displays extraction results:
- Toggle buttons for content types (Text, Files, JSON, Meta, Group)
- JSON Content displays structured results in YAML format
- Hierarchical artifact navigation (parent/child)

## Configuration Options

Configure via `TaskDefinition.task_runner_config`:

| Option | Default | Description |
|--------|---------|-------------|
| `global_search_mode` | `'intelligent'` | Search mode: `intelligent`, `skim_only`, `exhaustive_only` |
| `confidence_threshold` | `3` | Confidence level (1-5) to stop skim mode |
| `batch_size` | `5` | Pages per batch for extraction |
| `group_max_points` | `10` | Maximum fields per extraction group |
| `enable_context_pages` | `false` | Include adjacent pages for context |
| `adjacency_threshold` | varies | Pages within this range are grouped |
| `classification_context_before` | `0` | Context pages to include before |
| `classification_context_after` | `0` | Context pages to include after |
| `extraction_timeout` | `300` | LLM timeout in seconds |
| `extraction_instructions` | `null` | User-provided extraction instructions |
| `user_planning_hints` | `null` | Hints for the planning phase LLM |

### Search Modes Explained

**Intelligent (Default)**
- Uses skim mode for identity extraction (fast, stops when confident)
- Uses exhaustive mode for remaining extraction (thorough)
- Best balance of speed and accuracy

**Skim Only**
- Uses skim mode for all extractions
- Fastest but may miss data in large documents
- Good for well-structured documents

**Exhaustive Only**
- Processes all pages in single request
- Most thorough but slowest
- Best for critical data where nothing should be missed

## Debugging

### Debug Command

```bash
./vendor/bin/sail artisan debug:extract-data-task-run {id} --help
```

**Key Options:**

| Option | Description |
|--------|-------------|
| `--state-check` | Show state machine check results |
| `--run-orchestrator` | Run ExtractionStateOrchestrator::advanceToNextPhase() |
| `--run-process=ID` | Run specific process synchronously |
| `--show-schema=ID` | Show extraction response schema for process |
| `--cached-plan` | Show cached extraction plan |
| `--level-progress` | Show level progress tracking |
| `--classify-status` | Show classification process status |
| `--artifact-tree` | Show artifact hierarchy |
| `--resolved-objects` | Show all resolved TeamObjects |
| `--messages` | Show agent thread messages |
| `--api-logs` | Show API request/response logs |

### Common Debugging Scenarios

**Task stuck - not advancing to next phase:**
```bash
./vendor/bin/sail artisan debug:extract-data-task-run {id} --state-check
```

**Classification not working correctly:**
```bash
./vendor/bin/sail artisan debug:extract-data-task-run {id} --classify-status
```

**Extraction producing wrong results:**
```bash
./vendor/bin/sail artisan debug:extract-data-task-run {id} --process={process_id} --api-logs
```

**View the extraction schema sent to LLM:**
```bash
./vendor/bin/sail artisan debug:extract-data-task-run {id} --show-schema={process_id}
```

**Re-run a specific process:**
```bash
./vendor/bin/sail artisan debug:extract-data-task-run {id} --run-process={process_id}
```

## File Structure

```
app/
├── Services/Task/
│   ├── Runners/
│   │   ├── ExtractDataTaskRunner.php      # Main task runner
│   │   └── AgentThreadTaskRunner.php      # Base class
│   ├── DataExtraction/
│   │   ├── ExtractionStateOrchestrator.php
│   │   ├── ExtractionProcessOrchestrator.php
│   │   ├── PerObjectPlanningService.php
│   │   ├── PlanningPhaseService.php
│   │   ├── ExtractionPlanningService.php
│   │   ├── ClassificationOrchestrator.php
│   │   ├── ClassificationExecutorService.php
│   │   ├── IdentityExtractionService.php
│   │   ├── RemainingExtractionService.php
│   │   ├── GroupExtractionService.php
│   │   ├── DuplicateRecordResolver.php
│   │   ├── ArtifactPreparationService.php
│   │   ├── ExtractionArtifactBuilder.php
│   │   ├── ExtractionRollupService.php
│   │   └── ResolvedObjectsService.php
│   ├── Traits/
│   │   └── HasTranscodePrerequisite.php
│   └── TranscodePrerequisiteService.php
├── Console/Commands/Debug/DebugTaskRun/
│   └── DebugExtractDataTaskRunCommand.php
└── Models/Task/
    ├── TaskRun.php
    └── TaskProcess.php

spa/src/
├── components/Modules/TaskDefinitions/
│   ├── TaskRunners/Configs/
│   │   └── ExtractDataTaskRunnerConfig.vue
│   └── Panels/
│       ├── TaskRunCard.vue
│       └── TaskProcessCard.vue
├── components/Modules/Artifacts/
│   ├── ArtifactCard.vue
│   └── ArtifactList.vue
└── components/Modules/SchemaEditor/
    └── SchemaEditorToolbox.vue

tests/Feature/Services/Task/Runners/
└── ExtractDataTaskRunnerTest.php
```

## Key Design Patterns

### State Machine Pattern

ExtractionStateOrchestrator acts as a unified state machine, checking conditions in order and creating the appropriate processes for the next phase.

### Hierarchical Level Processing

Objects are processed level-by-level (parents before children) to ensure referential integrity. Parent IDs are stored in TaskRun.meta and passed to child-level processes.

### Classification-Driven Filtering

Pages are classified once using a boolean schema, then filtered per extraction group. This avoids re-reading irrelevant pages during extraction.

### Progressive Duplicate Search

Duplicate detection uses multiple search queries from specific to broad, with early termination on exact match and LLM comparison for candidates.

### Plan Caching

Extraction plans are cached in TaskDefinition.meta with a SHA-256 hash of schema + config. Plans are reused for identical configurations.

### Artifact-Based Rollup

Final output is built entirely from artifact data (no TeamObject queries during rollup), ensuring consistency and auditability.

## UI Workflow

### Setting Up an Extract Data Task

1. Navigate to Workflow Definitions
2. Drag "Extract Data" task from sidebar to canvas
3. Double-click node to open configuration
4. Configure:
   - Select LLM agent
   - Choose search strategy (Intelligent recommended)
   - Set grouping options
   - Enable context pages if needed
   - Add extraction instructions (optional)
   - Select output JSON schema
5. Connect to input artifacts from previous task
6. Save configuration

### Monitoring Extraction Progress

1. Run the workflow
2. Click on task node to expand
3. View TaskRunCard for overall progress
4. Expand to see individual TaskProcessCards
5. Check:
   - **Agent Thread** for LLM reasoning
   - **Output Artifacts** for extracted data
   - **JSON Content** for structured results

### Viewing Extraction Results

1. Navigate to Artifacts panel
2. Find parent artifact for the task run
3. Expand "Group" to see child artifacts
4. Toggle "Show Json Content" for YAML view
5. Use search to filter specific artifacts
