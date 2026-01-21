# Templates System Guide

This guide explains the template system in GPT Manager, including HTML template generation via LLM collaboration, variable mapping, and rendering.

## Overview

The templates system allows users to create reusable document templates (invoices, reports, letters, etc.) with dynamic variables that are populated from various data sources. Templates can be:

1. **HTML Templates** - Built collaboratively with an LLM, using a chat-based builder interface
2. **Google Docs Templates** - Synced from Google Docs with variable placeholders

## Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Frontend (Vue SPA)                          │
├─────────────────────────────────────────────────────────────────────┤
│  HtmlTemplateBuilderView    - Main view for template builder        │
│  HtmlTemplateBuilder        - Collaboration UI + preview            │
│  HtmlTemplatePreview        - Preview/Code/Building/Jobs tabs       │
│  CollaborationPanel         - Resizable chat sidebar                │
│  CollaborationChat          - Message list + input                  │
│  CollaborationMessageCard   - Individual message display            │
│  useTemplateCollaboration   - Real-time Pusher subscriptions        │
└─────────────────────────────────────────────────────────────────────┘
                                   │
                                   ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        Backend (Laravel)                            │
├─────────────────────────────────────────────────────────────────────┤
│  Models:                                                            │
│    TemplateDefinition       - Core template entity                  │
│    TemplateVariable         - Variable definitions + mappings       │
│    TemplateDefinitionHistory- Auto-versioning snapshots             │
│                                                                     │
│  Services (Three-Agent Architecture):                               │
│    TemplateCollaborationService - Fast conversation (gpt-5-nano)    │
│    TemplatePlanningService      - Complex planning layer            │
│    TemplateBuildingService      - Powerful builder (gpt-5.2-codex)  │
│    TemplateRenderingService     - Orchestrates rendering            │
│    HtmlRenderingService         - HTML template renderer            │
│    GoogleDocsRenderingService   - Google Docs template renderer     │
│    TemplateVariableResolutionService - Resolves variable values     │
│                                                                     │
│  Jobs:                                                              │
│    TemplateCollaborationJob - Queues collaboration messages         │
│    TemplatePlanningJob      - Queues complex planning tasks         │
│    TemplateBuildingJob      - Queues template builds                │
└─────────────────────────────────────────────────────────────────────┘
```

### Three-Agent Architecture

The template system uses a sophisticated three-agent pattern:

1. **Conversation Agent** (`TemplateCollaborationService`, model: `gpt-5-nano`)
   - Fast responses to user messages
   - Brief acknowledgments only - NO detailed planning
   - Dispatches to Planning or Building based on complexity

2. **Planning Agent** (`TemplatePlanningService`)
   - Creates detailed implementation plans for complex requests
   - Analyzes current template state
   - Outputs structured plan for Builder agent

3. **Builder Agent** (`TemplateBuildingService`, model: `gpt-5.2-codex`)
   - Powerful model for complex template generation
   - Actually modifies HTML/CSS content
   - Follows plans from Planning agent
   - Syncs template variables from generated content
   - Runs asynchronously via job dispatch

This separation provides fast conversation feedback, thoughtful planning for complex requests, and high-quality template generation.

## Data Models

### TemplateDefinition

**Location:** `app/Models/Template/TemplateDefinition.php`

The core template entity with support for two types: `html` and `google_docs`.

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | Primary key |
| `team_id` | int | Owning team (FK) |
| `user_id` | int | Creator (FK) |
| `type` | enum | `html` or `google_docs` |
| `name` | string | Unique name per team |
| `description` | text | Optional description |
| `category` | string | Optional categorization |
| `is_active` | bool | Active flag |
| `html_content` | longText | HTML markup (for HTML type) |
| `css_content` | text | CSS styles (for HTML type) |
| `stored_file_id` | int | Google Docs source file (FK) |
| `preview_stored_file_id` | int | Optional preview screenshot (FK) |
| `building_job_dispatch_id` | int | Current build job (FK) |
| `pending_build_context` | array | Context for pending builds |
| `metadata` | json | Flexible metadata storage |

**Relationships:**
- `templateVariables()` - HasMany TemplateVariable
- `history()` - HasMany TemplateDefinitionHistory
- `collaborationThreads()` - MorphMany AgentThread
- `buildingJobDispatch()` - BelongsTo JobDispatch
- `jobDispatches()` - HasMany JobDispatch
- `storedFile()` - BelongsTo StoredFile
- `team()`, `user()` - BelongsTo

**Auto-Versioning:** When `html_content` or `css_content` changes, a history record is automatically created via the `booted()` hook. The history stores the PREVIOUS values before the update.

### TemplateVariable

**Location:** `app/Models/Template/TemplateVariable.php`

Defines variables within a template and how they get their values.

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | Primary key |
| `template_definition_id` | int | Parent template (FK) |
| `name` | string | Variable name (unique per template) |
| `description` | text | Optional description |
| `mapping_type` | enum | `ai`, `artifact`, or `team_object` |
| `ai_instructions` | text | Instructions for AI extraction |
| `ai_prompt` | text | Prompt for AI variable resolution |
| `ai_context_fields` | json | Fields to include in AI context |
| `artifact_categories` | json | Filter artifacts by category |
| `artifact_fragment_selector` | json | JSONPath-like selector |
| `artifact_field` | string | Specific artifact field to extract |
| `artifact_format` | string | Format for artifact extraction |
| `team_object_schema_association_id` | int | Schema association (FK) |
| `team_object_field` | string | Specific team object field |
| `multi_value_strategy` | string | How to handle multiple values |
| `multi_value_separator` | string | Separator for joined values |
| `value_format_type` | string | Format type (text, currency, etc.) |
| `decimal_places` | int | Decimal precision (0-4) |
| `currency_code` | string | 3-char currency code |
| `default_value` | string | Default if no value resolved |
| `position` | int | Display/processing order |

**Mapping Types:**

1. **AI** (`mapping_type = 'ai'`) - LLM extracts value from artifacts using optional instructions
2. **Artifact** (`mapping_type = 'artifact'`) - Direct extraction from task artifacts with optional filtering
3. **TeamObject** (`mapping_type = 'team_object'`) - Extract from team objects via schema associations

**Multi-Value Strategies:**
- `join` - Join values with separator (default)
- `first` - Take first value only
- `unique` - Join unique values only
- `max`, `min`, `avg`, `sum` - Numeric aggregations

### TemplateDefinitionHistory

**Location:** `app/Models/Template/TemplateDefinitionHistory.php`

Stores version history with tiered retention policy.

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | Primary key |
| `template_definition_id` | int | Parent template (FK) |
| `user_id` | int | User who made the change (FK) |
| `html_content` | longText | HTML snapshot |
| `css_content` | text | CSS snapshot |
| `created_at` | timestamp | When version was created |

**Retention Policy:**
- Keep 1 per minute for last 15 minutes
- Keep 1 per hour for last 1 day
- Keep 1 per day for last 1 week

## Backend Services

### TemplateCollaborationService

**Location:** `app/Services/Template/TemplateCollaborationService.php`

Fast conversation agent for user interactions and initial collaboration setup.

**Model:** `gpt-5-nano` (configurable via `AI_TEMPLATE_COLLABORATION_MODEL` env var)

**Key Responsibilities:**
- Start new collaboration threads via `startCollaboration()`
- Handle user messages in real-time
- Provide brief acknowledgments only - NO detailed planning
- Determine when template modifications are needed
- Dispatch to Planning agent for complex requests or directly to Builder for simple changes

### TemplatePlanningService

**Location:** `app/Services/Template/TemplatePlanningService.php`

Planning agent for complex template modification requests.

**Key Responsibilities:**
- Create detailed implementation plans for complex requests
- Analyze current template state (HTML/CSS)
- Output structured plans for the Builder agent
- Run asynchronously via `TemplatePlanningJob`

### TemplateBuildingService

**Location:** `app/Services/Template/TemplateBuildingService.php`

Powerful builder agent for actual template modifications.

**Model:** `gpt-5.2-codex` (configurable via `AI_TEMPLATE_BUILDING_MODEL` env var)

**Key Responsibilities:**
- Generate/modify HTML and CSS content
- Follow plans from Planning agent for complex changes
- Process source files (PDFs, images) for template recreation
- Sync template variables from generated content
- Run asynchronously via `TemplateBuildingJob`

### TemplateDefinitionService

**Location:** `app/Services/Template/TemplateDefinitionService.php`

Handles CRUD operations and collaboration management.

**Key Methods:**
- `createTemplate(array $data)` - Create template, handles Google Docs URL conversion
- `updateTemplate(template, data)` - Update with ownership validation
- `fetchTemplateVariables(template)` - Extract variables from Google Docs
- `startCollaboration(template, fileIds[], prompt)` - Start LLM collaboration thread
- `sendMessage(thread, message, fileId?)` - Send message to collaboration
- `restoreVersion(history)` - Restore previous version

**Variable Convention:** HTML templates use `data-var-*` attributes to mark variable locations:
```html
<span data-var-customer_name>Customer Name</span>
<img data-var-logo_url src="placeholder.png" alt="Logo">
```

### TemplateRenderingService

**Location:** `app/Services/Template/TemplateRenderingService.php`

Orchestrates the complete rendering pipeline.

**Process:**
1. Resolve all variables via TemplateVariableResolutionService
2. Delegate to type-specific renderer (GoogleDocsRenderingService or HtmlRenderingService)
3. Return TemplateRenderResult with resolved values

### HtmlRenderingService

**Location:** `app/Services/Template/HtmlRenderingService.php`

Type-specific renderer for HTML templates.

**Responsibilities:**
- Replace `data-var-*` attributes with resolved values
- Handle image variables (src replacement)
- Process text content replacement

### GoogleDocsRenderingService

**Location:** `app/Services/Template/GoogleDocsRenderingService.php`

Type-specific renderer for Google Docs templates.

**Responsibilities:**
- Replace `{{variable}}` placeholders in Google Docs
- Coordinate with Google Docs API
- Handle document formatting preservation

### TemplateVariableResolutionService

**Location:** `app/Services/Demand/TemplateVariableResolutionService.php`

Resolves variable values from various sources.

**Resolution Phases:**
1. **Pre-resolve:** Artifact and TeamObject-mapped variables resolved directly
2. **AI Resolution:** AI-mapped variables resolved via LLM with artifacts as context
3. **Formatting:** All values formatted per variable's format settings

## Job Classes

### TemplateCollaborationJob

**Location:** `app/Jobs/TemplateCollaborationJob.php`

Queues collaboration messages for async processing.

### TemplatePlanningJob

**Location:** `app/Jobs/TemplatePlanningJob.php`

Queues complex planning tasks for async processing.

### TemplateBuildingJob

**Location:** `app/Jobs/TemplateBuildingJob.php`

Queues template builds for async processing with the powerful gpt-5.2-codex model.

## Frontend Components

### Views

#### HtmlTemplateBuilderView

**Location:** `spa/src/ui/templates/views/HtmlTemplateBuilderView.vue`

Main view for the template builder. Handles:
- Loading template data
- Managing collaboration thread state
- Optimistic message updates
- Version history modal
- Build job tracking and retry

**Route:** `/ui/templates/:id/builder` (name: `ui.template-builder`)

**Props/State:**
- `isLoadingJobDispatches` - Loading state for job history
- Template with build status tracking

**Events handled:**
- `@retry-build` - Retry a failed build
- `@load-job-dispatches` - Load build job history

### Components

#### HtmlTemplateBuilder

**Location:** `spa/src/components/Modules/Templates/HtmlTemplateBuilder.vue`

Main collaboration UI with two states:

1. **No thread:** File upload + prompt input to start collaboration
2. **Active thread:** CollaborationPanel with chat + preview

**Props:**
- `template` - TemplateDefinition
- `thread` - AgentThread (nullable)
- `loading` - Boolean
- `previewVariables` - Record<string, string>

**Events:**
- `start-collaboration` - (files: File[], prompt: string)
- `send-message` - (payload: SendMessagePayload)
- `screenshot-captured` - (requestId: string, file: File)
- `retry-build` - Retry a failed build job
- `load-job-dispatches` - Request job dispatch history

#### HtmlTemplatePreview

**Location:** `spa/src/components/Modules/Templates/HtmlTemplatePreview.vue`

Multi-tab template viewer:

1. **Preview tab:** Sandboxed iframe rendering HTML+CSS with variable highlighting
2. **Code tab:** Side-by-side HTML and CSS code viewers
3. **Building tab:** Shows current build status and progress
4. **Jobs tab:** Historical build job dispatches

**Features:**
- Extracts body content from full HTML documents (LLM sometimes returns complete documents)
- Strips `<script>` tags for security
- Highlights unresolved `{{variable}}` placeholders with yellow background
- Sandboxed iframe with `allow-scripts` only (no same-origin access)
- Build progress tracking with retry capability

**Props:**
- `html` - HTML content string
- `css` - CSS content string (optional)
- `variables` - Record<string, string> for placeholder replacement
- `building-job-dispatch` - Current build job dispatch
- `pending-build-context` - Context for pending build
- `job-dispatches` - Array of historical job dispatches
- `job-dispatch-count` - Total count of job dispatches
- `can-view-jobs` - Whether jobs tab is accessible
- `is-loading-job-dispatches` - Loading state for jobs

**Events:**
- `retry-build` - Retry a failed build
- `load-job-dispatches` - Load job dispatch history

#### CollaborationPanel

**Location:** `spa/src/components/Modules/Collaboration/CollaborationPanel.vue`

Resizable split-pane layout with:
- Left: Chat sidebar (CollaborationChat)
- Right: Preview slot
- Draggable resize handle
- Width persisted to localStorage

#### CollaborationChat

**Location:** `spa/src/components/Modules/Collaboration/CollaborationChat.vue`

Chat interface with:
- Message list (CollaborationMessageCard for each)
- File upload support
- Clipboard paste for images
- Message input with Ctrl+Enter to send
- Filters out system-generated prompts from display

#### CollaborationMessageCard

**Location:** `spa/src/components/Modules/Collaboration/CollaborationMessageCard.vue`

Individual message display with:
- Role-based styling (user vs assistant)
- Collapsible content
- "Thinking..." indicator for optimistic messages
- Screenshot request handling
- Code generation summary for HTML/CSS responses
- File attachment previews

### Composables

#### useTemplateCollaboration

**Location:** `spa/src/ui/templates/composables/useTemplateCollaboration.ts`

Real-time collaboration updates via Pusher WebSocket.

**Features:**
- Subscribes to multiple event sources:
  - `AgentThread.updated` - Thread changes
  - `TemplateDefinition.updated` - Building status changes
  - `JobDispatch.updated` - Build job progress
- Auto-reloads template when updates occur
- Debounces reloads (500ms cooldown) to prevent duplicate requests
- Cleanup on unmount

**Subscriptions:**
```typescript
// Thread updates (message creation triggers this via model touch)
await pusher.subscribeToModel("AgentThread", ["updated"], thread.id);

// Template updates (building status changes)
await pusher.subscribeToModel("TemplateDefinition", ["updated"], template.id);

// Job dispatch updates (build progress)
if (jobDispatchId) {
    await pusher.subscribeToModel("JobDispatch", ["updated"], jobDispatchId);
}
```

#### useTemplateDefinitions

**Location:** `spa/src/ui/templates/composables/useTemplateDefinitions.ts`

Template list management.

**Provides:**
- `activeTemplates` - List of active templates
- `isLoading` - Loading state
- `loadActiveTemplates()` - Fetch active templates

### Types

**Location:** `spa/src/ui/templates/types.ts`

```typescript
interface TemplateDefinition {
  id: number
  team_id: number
  name: string
  description?: string
  category?: string
  type: "google_docs" | "html"
  is_active: boolean
  stored_file?: UploadedFile
  html_content?: string
  css_content?: string
  preview_stored_file?: UploadedFile
  template_variables?: TemplateVariable[]
  history?: TemplateDefinitionHistory[]
  collaboration_threads?: AgentThread[]
  // Build job tracking
  template_url?: string
  google_doc_id?: string
  building_job_dispatch_id?: number
  pending_build_context?: string[]
  building_job_dispatch?: BuildingJobDispatch
  job_dispatches?: BuildingJobDispatch[]
  job_dispatch_count?: number
  metadata?: Record<string, any>
  created_at: string
  updated_at: string
}

interface TemplateVariable {
  id: number
  template_definition_id: number
  name: string
  description?: string
  mapping_type: "ai" | "artifact" | "team_object"
  // AI mapping
  ai_instructions?: string
  ai_prompt?: string
  ai_context_fields?: string[]
  // Artifact mapping
  artifact_categories?: string[]
  artifact_fragment_selector?: FragmentSelector
  artifact_field?: string
  artifact_format?: string
  // Team object mapping
  team_object_schema_association_id?: number
  schema_association_id?: number
  schema_association?: SchemaAssociation
  team_object_field?: string
  // Multi-value handling
  multi_value_strategy?: "join"|"first"|"unique"|"max"|"min"|"avg"|"sum"
  multi_value_separator?: string
  // Formatting
  value_format_type?: "text"|"integer"|"decimal"|"currency"|"percentage"|"date"
  decimal_places?: number
  currency_code?: string
  // Other
  default_value?: string
  position?: number
}

interface BuildingJobDispatch {
  id: number
  status: string
  progress?: number
  result?: any
  error?: string
  created_at: string
  updated_at: string
}

interface ScreenshotRequest {
  id: string
  status: "pending" | "in_progress" | "captured" | "failed"
  stored_file_id?: number
}
```

## Collaboration Workflow

### Starting a New Collaboration

1. User navigates to template builder (`/ui/templates/:id/builder`)
2. User enters prompt and/or uploads reference files (PDFs, images)
3. Clicks "Begin Collaboration"
4. Frontend calls `startCollaborationAction.trigger(template, { prompt, file_ids })`
5. Backend creates AgentThread linked to template via morphMany
6. TemplateCollaborationService initiates LLM conversation
7. Frontend subscribes to Pusher events for real-time updates

### Message Flow (Three-Agent Pattern)

1. User types message and clicks Send (or Ctrl+Enter)
2. Frontend adds optimistic messages:
   - User message (displayed immediately)
   - Assistant "thinking" message (shows spinner)
3. `sendMessageAction.trigger()` sends to backend
4. Backend queues `TemplateCollaborationJob` (fast gpt-5-nano)
5. Conversation agent responds with brief acknowledgment
6. Based on complexity:
   - **Complex requests:** Conversation agent dispatches `TemplatePlanningJob`
     - Planning agent creates detailed implementation plan
     - Planning agent dispatches `TemplateBuildingJob` with the plan
   - **Simple requests:** Conversation agent dispatches `TemplateBuildingJob` directly
7. Build job updates template HTML/CSS and syncs variables
8. Pusher events notify frontend of:
   - Thread updates (conversation messages)
   - Template updates (building status)
   - Job dispatch updates (build progress)
9. Frontend reloads template (debounced) to show changes

### Screenshot Feedback Loop

1. LLM can request a screenshot by including `screenshot_request` in response
2. CollaborationMessageCard shows "Capture" button
3. User clicks, CollaborationScreenshotCapture captures iframe
4. Screenshot uploaded to backend
5. LLM uses screenshot for visual feedback in next response

### Build Job Retry

1. If a build job fails, user sees error in Building tab
2. User clicks "Retry" button
3. Frontend emits `retry-build` event
4. Backend creates new `TemplateBuildingJob` with same context
5. Build progress tracked via Pusher

## File Structure

```
app/
├── Models/Template/
│   ├── TemplateDefinition.php
│   ├── TemplateDefinitionHistory.php
│   └── TemplateVariable.php
├── Services/Template/
│   ├── TemplateDefinitionService.php
│   ├── TemplateCollaborationService.php
│   ├── TemplatePlanningService.php
│   ├── TemplateBuildingService.php
│   ├── TemplateRenderingService.php
│   ├── HtmlRenderingService.php
│   └── GoogleDocsRenderingService.php
├── Services/Demand/
│   ├── TemplateVariableResolutionService.php
│   └── TemplateVariableService.php
├── Jobs/
│   ├── TemplateCollaborationJob.php
│   ├── TemplatePlanningJob.php
│   └── TemplateBuildingJob.php
├── Http/Controllers/Template/
│   └── TemplateDefinitionsController.php
└── Repositories/Template/
    ├── TemplateDefinitionRepository.php
    └── TemplateVariableRepository.php

spa/src/
├── ui/templates/
│   ├── config.ts                    # dxTemplateDefinition ActionController
│   ├── types.ts                     # TypeScript interfaces
│   ├── composables/
│   │   ├── useTemplateCollaboration.ts
│   │   └── useTemplateDefinitions.ts
│   └── views/
│       └── HtmlTemplateBuilderView.vue
├── components/Modules/Templates/
│   ├── HtmlTemplateBuilder.vue
│   ├── HtmlTemplatePreview.vue
│   ├── TemplateVariableEditor.vue
│   ├── Panels/
│   │   ├── TemplateInfoPanel.vue
│   │   ├── TemplateVariablesPanel.vue
│   │   ├── TemplateHistoryPanel.vue
│   │   └── TemplateCollaborationPanel.vue
│   └── index.ts
└── components/Modules/Collaboration/
    ├── CollaborationPanel.vue
    ├── CollaborationChat.vue
    ├── CollaborationMessageCard.vue
    ├── CollaborationFileUpload.vue
    ├── CollaborationScreenshotCapture.vue
    ├── CollaborationVersionHistory.vue
    ├── types.ts
    └── index.ts

routes/
└── api.php                          # ActionRoute for template-definitions
```

## API Routes

```
POST   /api/template-definitions              # Create
GET    /api/template-definitions              # List (with filters)
GET    /api/template-definitions/:id          # Show
PATCH  /api/template-definitions/:id          # Update
DELETE /api/template-definitions/:id          # Delete

# Actions (via ActionController)
POST   /api/template-definitions/:id/start-collaboration
POST   /api/template-definitions/:id/send-message
POST   /api/template-definitions/:id/restore-version
```

## Database Tables

```sql
-- template_definitions
id, team_id, user_id, type, name, description, category,
stored_file_id, html_content, css_content, preview_stored_file_id,
building_job_dispatch_id, pending_build_context,
is_active, metadata, created_at, updated_at, deleted_at

-- template_variables
id, template_definition_id, name, description, mapping_type,
ai_instructions, ai_prompt, ai_context_fields,
artifact_categories, artifact_fragment_selector, artifact_field, artifact_format,
team_object_schema_association_id, team_object_field,
multi_value_strategy, multi_value_separator,
value_format_type, decimal_places, currency_code,
default_value, position,
created_at, updated_at, deleted_at

-- template_definition_history
id, template_definition_id, user_id, html_content, css_content,
created_at, updated_at
```

## Key Patterns

### Variable Marking Convention

HTML templates use `data-var-*` attributes:
```html
<span data-var-customer_name>Default Text</span>
<img data-var-logo_url src="default.png">
<p data-var-description>Placeholder description</p>
```

### Auto-Versioning

Template changes automatically create history records with PREVIOUS values:
```php
// In TemplateDefinition::booted()
static::updating(function (TemplateDefinition $template) {
    if ($template->isDirty(['html_content', 'css_content'])) {
        TemplateDefinitionHistory::write($template);
    }
});
```

### Optimistic Updates

Frontend shows immediate feedback:
```typescript
// Add optimistic messages before API call
collaborationThread.value = {
    ...collaborationThread.value,
    messages: [...messages, userMessage, thinkingMessage],
    is_running: true
};

// API call replaces optimistic with real data
const result = await sendMessageAction.trigger(...);
collaborationThread.value = result.item.collaboration_threads[0];
```

### Multi-Source Pusher Subscription

Real-time updates via WebSocket for multiple event sources:
```typescript
// Subscribe to thread updates (covers message creation via model touch)
await pusher.subscribeToModel("AgentThread", ["updated"], thread.id);

// Subscribe to template updates (building status)
await pusher.subscribeToModel("TemplateDefinition", ["updated"], template.id);

// Subscribe to job dispatch updates (build progress)
if (buildingJobDispatchId) {
    await pusher.subscribeToModel("JobDispatch", ["updated"], buildingJobDispatchId);
}

// Handle updates with debounce
pusher.onEvent("AgentThread", "updated", async (data) => {
    if (Date.now() - lastReloadTime > 500) {
        await reloadTemplate();
    }
});
```
