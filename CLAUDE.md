# GPT Manager - LLM Code Writing Guide

## Core Principles

**ZERO TECH DEBT POLICY**

- NO legacy code - remove or refactor immediately
- NO backwards compatibility - always update to the right way
- ONE way to do everything - the correct, modern way
- Remove dead code on sight
- Refactor any code that doesn't meet standards
- Never use chmod on files to fix permissions!!! Always use sail artisan fix

## Laravel Backend Standards

### Architecture Patterns

**Service Layer Pattern**

```php
// Services contain ALL business logic
class TeamObjectMergeService
{
    public function merge(TeamObject $sourceObject, TeamObject $targetObject): TeamObject
    {
        $this->validateMerge($sourceObject, $targetObject);
        
        return DB::transaction(function () use ($sourceObject, $targetObject) {
            $this->mergeAttributes($sourceObject, $targetObject);
            $this->mergeRelationships($sourceObject, $targetObject);
            $sourceObject->delete();
            return $targetObject->fresh(['attributes', 'relationships']);
        });
    }
}
```

**Repository Pattern**

```php
// Repositories handle data access ONLY
// Except for the action() method which contains ALL endpoint actions for the given model
class AgentRepository extends ActionRepository
{
    public static string $model = Agent::class;

    public function query(): Builder
    {
        return parent::query()->where('team_id', team()->id);
    }
}
```

**Controller Pattern**

```php
// Controllers are THIN - validation and delegation only
class TeamObjectsController extends ActionController
{
    public static string $repo = TeamObjectRepository::class;
    public static ?string $resource = TeamObjectResource::class;

    // Use app() helper for service resolution (ActionRoute compatibility)
    public function merge(TeamObject $sourceObject, TeamObject $targetObject)
    {
        $mergedObject = app(TeamObjectMergeService::class)->merge($sourceObject, $targetObject);
        return new TeamObjectResource($mergedObject);
    }
}
```

### Model Best Practices

- Models contain ONLY: relationships, scopes, casts, attributes, simple queries
- Use traits for shared functionality (AuditableTrait, SoftDeletes)
- Implement validation via validate() method
- NO business logic in models

### API Standards

- Use ActionRoute::routes() for CRUD endpoints
- RESTful resource routing
- Resources handle API transformation
- Form requests for validation

### Database Standards

- Use `sail artisan make:migration` for migrations
- Anonymous class migrations (Laravel 9+ style)
- Proper foreign key constraints
- Composite indexes for performance

## Vue.js Frontend Standards

### Component Architecture

**Single File Components**

```vue

<template>
    <!-- Keep templates clean and semantic -->
    <div class="component-root">
        <ChildComponent v-if="condition" />
    </div>
</template>

<script setup lang="ts">
    // ALWAYS use <script setup> with TypeScript
    import { computed, ref } from "vue";

    // Props with defaults
    const props = withDefaults(defineProps<{
        object: TeamObject;
        level?: number;
    }>(), {
        level: 0
    });

    // Emits typed
    const emit = defineEmits<{
        'update': [value: string];
    }>();

    // Reactive state
    const isLoading = ref(false);

    // Computed properties
    const displayName = computed(() => props.object.name || 'Untitled');

    // Functions
    async function handleAction() {
        isLoading.value = true;
        try {
            // Logic here
        } finally {
            isLoading.value = false;
        }
    }
</script>

<style lang="scss" scoped>
    // Minimal scoped styles only when necessary
</style>
```

### Component Guidelines

- KEEP COMPONENTS SMALL - extract to child components early
- One component = one responsibility
- Extract complex logic to composables
- Use TypeScript for all props, emits, and functions

### State Management

- Use reactive refs and custom stores (no Vuex/Pinia needed)
- Leverage quasar-ui-danx storeObjects for normalized data
- Real-time updates via Pusher

### Styling Standards

- Tailwind CSS utility classes ONLY
- NO inline styles
- Complex utility combinations should move to `<style scoped lang="scss">` sections
- Global styles use `@apply` directive in `spa/src/assets/*.scss` files
- Use Quasar components with Tailwind classes

### File Organization

```
spa/src/
├── components/
│   ├── Modules/         # Domain-specific modules
│   │   └── [Module]/
│   │       ├── config/  # Module configuration
│   │       ├── types.ts # TypeScript interfaces
│   │       ├── routes.ts # API routes
│   │       └── *.vue    # Components
│   ├── Layouts/         # Page layouts
│   └── Shared/          # Reusable components
├── helpers/             # Composables and utilities
├── types/               # Global TypeScript types
└── views/               # Top-level route views
```

## Coding Style Rules

### General

- NO comments unless absolutely necessary for complex logic
- Clear, self-documenting code
- Descriptive variable and function names
- Early returns to reduce nesting

### PHP/Laravel

- Use app() helper for service resolution in ActionControllers (not constructor DI)
- Database transactions for multi-step operations
- Custom exceptions with proper HTTP codes
- Team-based data scoping automatic
- Type declarations for all parameters and returns

### TypeScript/Vue

- Full TypeScript - no 'any' types
- Interface definitions for all data structures
- Composables for reusable logic
- Props validation with TypeScript

### Naming Conventions

- **Files**: PascalCase for components, camelCase for TS/JS
- **Routes**: kebab-case
- **Database**: snake_case
- **Classes/Interfaces**: PascalCase
- **Functions/Variables**: camelCase

## Development Workflow

### Before Writing Code

1. Check existing patterns in neighboring files
2. Use existing libraries - DON'T assume availability
3. Follow established conventions exactly

### When Writing Code

1. Start with the simplest working solution
2. Extract to service/repository/component when needed
3. NO premature optimization
4. Delete old code immediately

### Testing & Validation

- Run lint/typecheck commands if provided
- Verify no console errors
- Check for proper error handling
- Ensure proper loading states

## Common Patterns to Use

### API Integration

```typescript
// ALWAYS use request from quasar-ui-danx - NEVER use axios directly
import { request } from "quasar-ui-danx";

// Make API calls with automatic authentication
const response = await request.post('/api/endpoint', data);
const response = await request.get('/api/endpoint');

// Use configured routes
const { list, details, update } = dxAgent.routes;

// With loading states
const action = getAction('update');
await action.trigger(object, data);
```

### Form Handling

```vue

<EditableDiv
    :model-value="object.name"
    @update:model-value="name => updateAction.trigger(object, {name})"
/>
```

### Conditional Rendering

```vue
<!-- Use v-if for conditional rendering -->
<div v-if="hasPermission">...</div>

<!-- Use v-show for visibility toggle -->
<div v-show="isExpanded">...</div>
```

## Anti-Patterns to Avoid

### Backend

- Business logic in controllers or models
- Direct DB queries in controllers
- Mixed concerns in services
- Synchronous heavy operations

### Frontend

- Large monolithic components
- Direct API calls in components
- State management in components
- Inline styles or style attributes

## Migration Strategy

When encountering legacy code:

1. Identify the correct pattern
2. Refactor immediately - don't work around it
3. Update all related code to match
4. Remove dead code paths
5. Test the refactored solution

## Custom Libraries (quasar-ui-danx & danx-icon)

### quasar-ui-danx Library

**Core Philosophy**: Comprehensive UI component library that replaces Vuex/Pinia with `storeObjects` pattern.

**Key Components**:
- **Forms**: `TextField`, `NumberField`, `SelectField`, `DateField`, `MultiFileField`
- **Actions**: `ActionButton`, `ActionTableLayout`, `ActionForm`, `ActionMenu`
- **Layout**: `PanelsDrawer`, `CollapsableSidebar`, `EditableDiv`, `ShowHideButton`
- **Display**: `LabelPillWidget`, `FilePreview`, `ListTransition`, `SaveStateIndicator`
- **Dialogs**: `ConfirmDialog`, `RenderedFormDialog`, `FullScreenDialog`

**State Management with storeObjects**:
```typescript
// storeObjects - Reactive normalized data storage (replaces Vuex/Pinia)
import { storeObjects, storeObject } from "quasar-ui-danx";

// Store array of objects with reactive updates
workflowDefinitions.value = storeObjects((await dxWorkflowDefinition.routes.list()).data);

// Store single object (auto-updates across all components)
storeObject(updatedObject);

// Real-time updates via Pusher automatically call storeObject
channel.bind(event, function (data) {
    storeObject(data); // Updates all components using this object
});
```

**Controller Pattern**:
```typescript
// Each module uses a DanxController for CRUD operations
const dxAgent = new DanxController("agents", {
    routes: useActionRoutes("agents"),
    actions: {
        create: { optimistic: true },
        update: { immediate: true },
        delete: { confirm: true }
    }
});

// Usage in components
const updateAction = dxAgent.getAction("update");
await updateAction.trigger(object, data);
```

**Common Patterns**:
- Use `ActionTableLayout` for complete CRUD tables
- Use `PanelsDrawer` for detail panels
- Use formatting functions: `fDate`, `fNumber`, `fCurrency`, `fPercent`
- Use `ActionButton` with semantic types: "create", "trash", "edit"

### danx-icon Library

**Available Icon Types**:
- `FaSolid*` - Solid/filled icons (most common, 1500+ icons)
- `FaRegular*` - Outline/regular icons (~200 icons)  
- `FaBrands*` - Brand/company logos

**Usage Pattern**:
```typescript
import {
    FaSolidPencil as EditIcon,
    FaSolidTrash as DeleteIcon,
    FaRegularUser as UserIcon
} from "danx-icon";

// In template
<QBtn><EditIcon class="w-3" /></QBtn>
<ShowHideButton :show-icon="EditIcon" />
```

**Icon Categories**:
- Actions: Edit, Delete, Create, Save, Merge
- Navigation: Chevron, Arrow directions
- Status: Check, X, Warning, Info
- Content: File, Image, Text, Database
- UI: User, Settings, Search, Filter

### Tailwind CSS Best Practices

**When to use different approaches**:

1. **Simple utilities** - Use Tailwind classes directly:
```vue
<div class="bg-slate-900 text-white p-4 rounded-lg">
```

2. **Complex combinations** - Move to scoped styles:
```vue
<style lang="scss" scoped>
.team-object-header {
    .edit-button {
        transition: all 0.3s;
        opacity: 0;
    }
    
    &:hover .edit-button {
        opacity: 1;
    }
}
</style>
```

3. **Global patterns** - Use `@apply` in `/spa/src/assets/*.scss`:
```scss
// In general.scss
.flex-x {
    @apply flex items-center flex-nowrap;
}

body {
    @apply bg-slate-600 text-slate-200;
}

@layer utilities {
    .bg-skipped {
        background-image: repeating-linear-gradient(
            135deg,
            theme("colors.yellow.700") 0 10px,
            theme("colors.gray.700") 10px 20px
        );
    }
}
```

## Docker/Sail Commands

- Use `sail artisan` for all artisan commands
- Run `sail artisan fix` for permission issues
- Never modify git state without explicit instruction

## Summary

Write production-ready code that is:

- Clean and maintainable
- Following established patterns
- Type-safe and properly validated
- Refactored on sight if not meeting standards
- Free of legacy patterns or dead code
- Using quasar-ui-danx components and storeObjects for state
- Using danx-icon for all iconography needs

Remember: ONE RIGHT WAY - NO EXCEPTIONS!
