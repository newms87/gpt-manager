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
│  HtmlTemplatePreview        - Iframe preview + code viewer          │
│  CollaborationPanel         - Resizable chat sidebar                │
│  CollaborationChat          - Message list + input                  │
│  CollaborationMessageCard   - Individual message display            │
│  useTemplateCollaboration   - Real-time Pusher subscription         │
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
│  Services:                                                          │
│    TemplateDefinitionService     - CRUD + collaboration management  │
│    HtmlTemplateGenerationService - LLM-driven template building     │
│    TemplateRenderingService      - Orchestrates rendering           │
│    TemplateVariableResolutionService - Resolves variable values     │
└─────────────────────────────────────────────────────────────────────┘
```

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
| `metadata` | json | Flexible metadata storage |

**Relationships:**
- `templateVariables()` - HasMany TemplateVariable
- `history()` - HasMany TemplateDefinitionHistory
- `collaborationThreads()` - MorphMany AgentThread
- `storedFile()` - BelongsTo StoredFile
- `team()`, `user()` - BelongsTo

**Auto-Versioning:** When `html_content` or `css_content` changes, a history record is automatically created via the `booted()` hook.

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
| `artifact_categories` | json | Filter artifacts by category |
| `artifact_fragment_selector` | json | JSONPath-like selector |
| `team_object_schema_association_id` | int | Schema association (FK) |
| `multi_value_strategy` | string | How to handle multiple values |
| `multi_value_separator` | string | Separator for joined values |
| `value_format_type` | string | Format type (text, currency, etc.) |
| `decimal_places` | int | Decimal precision (0-4) |
| `currency_code` | string | 3-char currency code |

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

### HtmlTemplateGenerationService

**Location:** `app/Services/Template/HtmlTemplateGenerationService.php`

Drives the LLM-based template building process.

**How It Works:**
1. Creates/uses "Template Builder" agent
2. Accepts PDFs/images as source files + user prompt
3. LLM generates JSON response: `{html_content, css_content, variable_names, screenshot_request}`
4. Service updates template and syncs variables
5. If screenshot requested, message data stores the request

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

### TemplateVariableResolutionService

**Location:** `app/Services/Demand/TemplateVariableResolutionService.php`

Resolves variable values from various sources.

**Resolution Phases:**
1. **Pre-resolve:** Artifact and TeamObject-mapped variables resolved directly
2. **AI Resolution:** AI-mapped variables resolved via LLM with artifacts as context
3. **Formatting:** All values formatted per variable's format settings

## Frontend Components

### Views

#### HtmlTemplateBuilderView

**Location:** `spa/src/ui/templates/views/HtmlTemplateBuilderView.vue`

Main view for the template builder. Handles:
- Loading template data
- Managing collaboration thread state
- Optimistic message updates
- Version history modal

**Route:** `/ui/templates/:id/builder` (name: `ui.template-builder`)

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

#### HtmlTemplatePreview

**Location:** `spa/src/components/Modules/Templates/HtmlTemplatePreview.vue`

Dual-mode template viewer:

1. **Preview tab:** Sandboxed iframe rendering HTML+CSS with variable highlighting
2. **Code tab:** Side-by-side HTML and CSS code viewers

**Features:**
- Extracts body content from full HTML documents (LLM sometimes returns complete documents)
- Strips `<script>` tags for security
- Highlights unresolved `{{variable}}` placeholders with yellow background
- Sandboxed iframe with `allow-scripts` only (no same-origin access)

**Props:**
- `html` - HTML content string
- `css` - CSS content string (optional)
- `variables` - Record<string, string> for placeholder replacement

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
- Subscribes to `AgentThread.updated` events
- Auto-reloads template when thread updates (new messages, status changes)
- Debounces reloads (500ms cooldown) to prevent duplicate requests
- Cleanup on unmount

**Note:** Only subscribes to `AgentThread.updated` - message creation triggers this via model touch.

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
  created_at: string
  updated_at: string
}

interface TemplateVariable {
  id: number
  template_definition_id: number
  name: string
  description?: string
  mapping_type: "ai" | "artifact" | "team_object"
  ai_instructions?: string
  artifact_categories?: string[]
  artifact_fragment_selector?: FragmentSelector
  team_object_schema_association_id?: number
  multi_value_strategy?: "join"|"first"|"unique"|"max"|"min"|"avg"|"sum"
  multi_value_separator?: string
  value_format_type?: "text"|"integer"|"decimal"|"currency"|"percentage"|"date"
  decimal_places?: number
  currency_code?: string
}
```

## Collaboration Workflow

### Starting a New Collaboration

1. User navigates to template builder (`/ui/templates/:id/builder`)
2. User enters prompt and/or uploads reference files (PDFs, images)
3. Clicks "Begin Collaboration"
4. Frontend calls `startCollaborationAction.trigger(template, { prompt, file_ids })`
5. Backend creates AgentThread linked to template via morphMany
6. HtmlTemplateGenerationService initiates LLM conversation
7. Frontend subscribes to Pusher events for real-time updates

### Message Flow

1. User types message and clicks Send (or Ctrl+Enter)
2. Frontend adds optimistic messages:
   - User message (displayed immediately)
   - Assistant "thinking" message (shows spinner)
3. `sendMessageAction.trigger()` sends to backend
4. Backend adds message to thread, triggers LLM response
5. LLM generates response with `{html_content, css_content, variable_names}`
6. Backend updates template, syncs variables
7. AgentThread is touched, triggering Pusher event
8. Frontend receives event, reloads template (debounced)
9. Real messages replace optimistic ones

### Screenshot Feedback Loop

1. LLM can request a screenshot by including `screenshot_request` in response
2. CollaborationMessageCard shows "Capture" button
3. User clicks, CollaborationScreenshotCapture captures iframe
4. Screenshot uploaded to backend
5. LLM uses screenshot for visual feedback in next response

## File Structure

```
app/
├── Models/Template/
│   ├── TemplateDefinition.php
│   ├── TemplateDefinitionHistory.php
│   └── TemplateVariable.php
├── Services/Template/
│   ├── TemplateDefinitionService.php
│   ├── HtmlTemplateGenerationService.php
│   └── TemplateRenderingService.php
├── Services/Demand/
│   ├── TemplateVariableResolutionService.php
│   └── TemplateVariableService.php
├── Http/Controllers/Template/
│   └── TemplateDefinitionsController.php
└── Repositories/Template/
    └── TemplateDefinitionRepository.php

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
is_active, metadata, created_at, updated_at, deleted_at

-- template_variables
id, template_definition_id, name, description, mapping_type,
ai_instructions, artifact_categories, artifact_fragment_selector,
team_object_schema_association_id, multi_value_strategy,
multi_value_separator, value_format_type, decimal_places,
currency_code, created_at, updated_at, deleted_at

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

Template changes automatically create history records:
```php
// In TemplateDefinition::booted()
static::updating(function (TemplateDefinition $template) {
    if ($template->isDirty(['html_content', 'css_content'])) {
        TemplateDefinitionHistory::createFromTemplate($template);
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

### Pusher Subscription

Real-time updates via WebSocket:
```typescript
// Subscribe to thread updates (covers message creation via model touch)
await pusher.subscribeToModel("AgentThread", ["updated"], thread.id);

// Handle updates with debounce
pusher.onEvent("AgentThread", "updated", async (data) => {
    if (Date.now() - lastReloadTime > 500) {
        await reloadTemplate();
    }
});
```
