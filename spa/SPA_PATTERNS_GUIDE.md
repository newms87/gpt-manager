# GPT Manager SPA Patterns Guide

This guide documents the reusable components, patterns, and conventions used throughout the GPT Manager SPA codebase.

## 1. Quasar-UI-Danx Components

### Tables & Lists

**ActionTableLayout** - Complete CRUD table with filters, pagination, and actions

```vue

<ActionTableLayout
    title="Agents"
    :controller="dxAgent"
    table-class="bg-slate-600"
    filter-class="bg-slate-500"
    show-filters
    refresh-button
    create-button
/>
```

**ListTransition** - Animated list transitions

```vue

<ListTransition>
    <div v-for="item in items" :key="item.id">...</div>
</ListTransition>
```

### Forms & Inputs

**TextField** - Text input with validation

```vue

<TextField
    v-model="input.name"
    label="Name"
    required
    :max-length="40"
    @update:model-value="onUpdate"
/>
```

**NumberField** - Numeric input with min/max

```vue

<NumberField
    v-model="input.polling_interval"
    label="Polling Interval (in minutes)"
    :min="1"
    :max="60*60*24*365"
    prepend-label
/>
```

**SelectField** - Dropdown selection

```vue

<SelectField
    v-model="selectedOption"
    label="Choose Option"
    :options="availableOptions"
/>
```

**DateField** - Date picker

```vue

<DateField
    v-model="date"
    label="Select Date"
/>
```

**MultiFileField** - File upload with preview

```vue

<MultiFileField
    v-model="files"
    label="Upload Files"
    multiple
/>
```

**EditableDiv** - Inline editable text

```vue

<EditableDiv
    :model-value="object.name"
    placeholder="Enter name..."
    color="slate-800"
    @update:model-value="name => updateAction.trigger(object, {name})"
/>
```

**SelectionMenuField** - Complex selection with CRUD operations

```vue

<SelectionMenuField
    v-model:selected="agent"
    selectable
    editable
    deletable
    creatable
    :options="availableAgents"
    @create="createAction.trigger()"
/>
```

### Actions & Buttons

**ActionButton** - Semantic action buttons

```vue
<ActionButton
    type="create"
    label="Create New"
    color="blue-invert"
    tooltip="Create New"
    :loading="action.isApplying"
    @click="action.trigger()"
/>
```

**ActionButton Rules:**
- Use `type` prop - each type has a predefined icon (create=plus, cancel=X, etc.)
- Use `label` prop for text
- Available types: create, edit, delete, trash, cancel, confirm, save, export, import, play, stop, pause, refresh, restart, merge, check, view, etc.

**ShowHideButton** - Toggle visibility

```vue

<ShowHideButton
    v-model="isShowing"
    label="Details"
    @show="onShow"
/>
```

### Layout Components

**PanelsDrawer** - Slide-out detail panels

```vue

<PanelsDrawer
    v-model="activeItem"
    :title="activeItem?.name"
    :panels="panels"
/>
```

**CollapsableSidebar** - Collapsible navigation sidebar

```vue

<CollapsableSidebar
    v-model="isOpen"
    :width="300"
>
    <NavigationMenu :items="navItems" />
</CollapsableSidebar>
```

**ActionForm** - Form wrapper with actions

```vue

<ActionForm
    :action="updateAction"
    :target="object"
>
    <!-- Form fields -->
</ActionForm>
```

### Display Components

**LabelPillWidget** - Status/label pills

```vue

<LabelPillWidget
    label="Status"
    value="Active"
    color="green"
/>
```

**FilePreview** - File preview with actions

```vue

<FilePreview
    :file="uploadedFile"
    downloadable
    removable
/>
```

**SaveStateIndicator** - Save status indicator

```vue

<SaveStateIndicator
    :saving="isSaving"
    :saved="isSaved"
/>
```

### Dialogs

**ConfirmDialog** - Confirmation dialogs

```vue

<ConfirmDialog
    v-if="showConfirmDialog"
    title="Delete Item?"
    content="This action cannot be undone."
    confirm-label="Delete"
    confirm-class="bg-red-700 text-red-200"
    @confirm="deleteItem"
/>
```

**InfoDialog** - Information dialogs

```vue

<InfoDialog
    v-if="showInfoDialog"
    title="Process Details"
    :content="processInfo"
/>
```

### Utilities

**PaginationNavigator** - Robust pagination with page size selector

```vue
<script setup>
import PaginationNavigator from "@/components/Shared/Utilities/PaginationNavigator.vue";

const pagination = ref({
  page: 1,
  perPage: 10,
  total: 0
});
</script>

<template>
  <PaginationNavigator
    v-model="pagination"
    :page-sizes="[10, 20, 50, 100]"
    :default-size="10"
  />
</template>
```

Features:
- Per page size selector with customizable options
- First/previous/next/last navigation buttons
- Smart page number display with ellipsis
- "Go to page" input for quick navigation
- Item range display (e.g., "1-10 of 45 items")
- Always visible (even with 1 page)

## 2. State Management with ActionRoutes

**CRITICAL: NEVER use storeObject() or storeObjects() directly**

```typescript
// Routes handle all state management automatically
const result = await routes.list();
items.value = result.data;
await routes.details(object, fields); // Updates existing object in-place

// Performance optimized loading - relations are auto-hydrated on the object
const loadBasicData = async (item) => {
    await routes.details(item, { user: true, files: true });
    // item.user and item.files are now populated - NO need to manually store results
};

const loadHeavyData = async (item) => {
    await routes.details(item, { usage_events: { user: true } });
};

// On-demand loading pattern (e.g., loading related data when user clicks to view)
// Use @show callback on ShowHideButton - NOT watch() which is an anti-pattern
async function loadRelated() {
    if (!props.item.related && !isLoading.value) {
        isLoading.value = true;
        try {
            await routes.details(props.item, { related: true });
            // props.item.related is now populated automatically via storedObject()
        } finally {
            isLoading.value = false;
        }
    }
}

// In template:
// <ShowHideButton v-model="showDetails" :loading="isLoading" @show="loadRelated" />
```

**Key Principles:**

- Routes handle all state management automatically
- Objects are stored once per `id + __type` combination
- All references point to the same instance
- Updates reflect everywhere automatically
- No manual array management needed

**Local Storage Helpers:**

```typescript
import { getItem, setItem } from "quasar-ui-danx";

setItem("key", value);
const value = getItem("key");
```

## 3. Controller Pattern (DanxController)

```typescript
// Define controller configuration
export const dxAgent = {
    ...controls,
    ...actionControls,
    columns,
    filters,
    fields,
    panels,
    routes
} as DanxController<Agent>;

// Use controller actions
const updateAction = dxAgent.getAction("update");
const createAction = dxAgent.getAction("create", {
    optimistic: true,
    onFinish: loadAgents
});

// Initialize controller
dxAgent.initialize();
```

## 4. Icon Usage (danx-icon)

### Common Icons

```typescript
// Solid icons (most common)
import {
    FaSolidPencil as EditIcon,
    FaSolidTrash as DeleteIcon,
    FaSolidPlus as CreateIcon,
    FaSolidCheck as CheckIcon,
    FaSolidX as CloseIcon,
    FaSolidGear as SettingsIcon,
    FaSolidUser as UserIcon,
    FaSolidChevronDown as DropdownIcon,
    FaSolidMagnifyingGlass as SearchIcon,
    FaSolidFilter as FilterIcon,
    FaSolidArrowRotateRight as RefreshIcon
} from "danx-icon";

// Regular/outline icons
import {
    FaRegularUser as UserOutlineIcon,
    FaRegularMessage as MessageIcon
} from "danx-icon";

// Usage
<EditIcon class = "w-3" / >  // Use Tailwind width classes
```

## 5. File Organization

```
spa/src/
├── components/
│   ├── Modules/          # Domain-specific components
│   │   └── [Module]/
│   │       ├── config/   # Controller configuration
│   │       │   ├── actions.ts
│   │       │   ├── columns.ts
│   │       │   ├── controls.ts
│   │       │   ├── fields.ts
│   │       │   ├── filters.ts
│   │       │   ├── panels.ts
│   │       │   └── routes.ts
│   │       ├── Dialogs/
│   │       ├── Fields/
│   │       ├── Panels/
│   │       ├── store.ts  # Module state management
│   │       └── index.ts
│   ├── Layouts/
│   └── Shared/
├── composables/          # Reusable composition functions
├── helpers/              # Utility functions
├── types/                # TypeScript definitions
└── views/                # Route views
```

## 6. Common Patterns

### API Requests

```typescript
import { request } from "quasar-ui-danx";

// GET request
const response = await request.get('/api/endpoint');

// POST with data
const result = await request.post('/api/endpoint', data);

// Using route helpers
const { list, details, update } = dxAgent.routes;
await list({ filter: { active: true } });
```

### Actions with Loading States

```typescript
const action = dxAgent.getAction("update");

// In template
<QBtn
:
loading = "action.isApplying"
@click
= "action.trigger(object, data)" >
    Save
    < /QBtn>
```

### Reactive Form Updates

```vue

<TextField
    v-model="input.name"
    @update:model-value="updateAction.trigger(object, input)"
/>
```

### Conditional Rendering

```vue
<!-- Use v-if for conditional DOM -->
<div v-if="hasPermission">...</div>

<!-- Use v-show for visibility toggle -->
<div v-show="isExpanded">...</div>
```

## 7. API Data Loading - ActionResource Pattern

This project uses danx's ActionResource pattern. Understanding how fields are loaded is critical.

### Field Types (Backend)

1. **Scalar fields** - Always included in responses (id, name, foreign keys)
2. **Callable fields** - Only included when explicitly requested (relationships, computed data)

### Requesting Callable Fields

```typescript
// Basic - only scalar fields returned
const template = await dxTemplateDefinition.routes.details({ id: templateId });
// Returns: { id, name, schema_definition_id, ... }
// Does NOT return: schema_definition (it's callable)

// Request specific relationships
const template = await dxTemplateDefinition.routes.details({ id: templateId }, {
    schema_definition: true,
    artifacts: true
});
// Now includes: schema_definition, artifacts

// Request nested relationships
const template = await dxTemplateDefinition.routes.details({ id: templateId }, {
    schema_definition: { fragments: true, associations: true }
});
// Includes schema_definition WITH its fragments and associations
```

### When Relationship Data Is Missing

If you have `schema_definition_id` but not `schema_definition`:

**The fix is ALWAYS in your API call** - add `{ schema_definition: true }` to the request.

- **WRONG**: Ask backend to add eager loading
- **RIGHT**: Request the field in your API call

### Lazy Loading Relationships (Performance)

**DO NOT load all relationships upfront.** Load them lazily when the user needs them.

```typescript
// Initial page load - only default scalar data
const template = ref(null);
template.value = await routes.details({ id });

// Later, when user opens a tab or expands a section - load the relationship
await routes.details(template.value, { schema_definition: true });
// NOTE: No reassignment needed! The storeObject() system automatically
// populates the relationship on the existing object EVERYWHERE it's used
// (even in lists and other pages)
```

**When to load relationships:**
- When a tab containing the data is opened
- When a collapsed section is expanded
- When a hidden section becomes visible
- NOT on initial page load (unless immediately visible)

**Key insight:** The `storeObject()` system used by `routes.list()` and `routes.details()` automatically merges loaded data into existing ActionResource objects. Loading a relationship updates that object everywhere it's referenced.

### List vs Details

- `routes.list()` - Usually returns minimal data (scalars only)
- `routes.details()` - Can request full relationships via second parameter

## 8. Styling Patterns

### Tailwind Classes

```vue
<!-- Background colors -->
<div class="bg-slate-600 bg-slate-700 bg-slate-800 bg-slate-900">

    <!-- Text colors -->
    <div class="text-slate-200 text-slate-300 text-slate-400">

        <!-- Spacing -->
        <div class="p-4 px-6 py-3 mt-4 space-x-3">

            <!-- Flexbox -->
            <div class="flex items-center justify-between gap-4">

                <!-- Rounded corners -->
                <div class="rounded rounded-lg rounded-xl">
```

### Custom Utility Classes

```scss
// In assets/general.scss
.flex-x {
    @apply flex items-center flex-nowrap;
}

// Special backgrounds
.bg-skipped {
    background-image: repeating-linear-gradient(
            135deg,
            theme("colors.yellow.700") 0 10px,
            theme("colors.gray.700") 10px 20px
    );
}
```

### Component-Specific Styles

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

## 9. Common Composables

### useAssistantState

```typescript
import { useAssistantState } from "@/composables/useAssistantState";

const {
    activeActions,
    contextCapabilities,
    handleChatActions,
    approveAction,
    rejectAction
} = useAssistantState();
```

### Authentication Helpers

```typescript
import { authUser, authTeam, isAuthenticated, setAuthToken } from "@/helpers/auth";

if (isAuthenticated()) {
    // User is logged in
}
```

### Pusher Integration

```typescript
import { usePusher } from "@/helpers/pusher";

const pusher = usePusher();
pusher.onEvent("Model", "updated", (data) => {
    storeObject(data);
});
```

## 10. Formatting Functions

**CRITICAL: Formatters MUST be used 100% of the time for all data display - NEVER use raw values or create custom formatting**

All formatting utilities come from `quasar-ui-danx/helpers/formats/` module, organized into:
- `datetime.ts` - Date/time formatting (fDate, fDateTime, fTimeAgo, fDuration)
- `numbers.ts` - Number formatting (fNumber, fShortNumber, fPercent)
- `currency.ts` - Currency formatting (fCurrency, fCurrencyNoCents, fShortCurrency)
- `strings.ts` - String utilities (fBoolean, fPhone, fShortSize)
- `parsers.ts` - Parsing utilities (parseDate, parseNumber, etc.)

**Usage:**
```typescript
import { fDate, fCurrency, fNumber } from "quasar-ui-danx";

// In template or computed
fDate(date);              // "Jan 1, 2024"
fCurrency(1234.56);       // "$1,234.56"
fNumber(1234.56);         // "1,234.56"
```

**For complete function signatures and all available formatters, read the source files in quasar-ui-danx/helpers/formats/**

## 11. TypeScript Patterns

### Component Props

```typescript
const props = withDefaults(defineProps<{
    object: TeamObject;
    level?: number;
}>(), {
    level: 0
});
```

### Emits

```typescript
const emit = defineEmits<{
    'update': [value: string];
    'delete': [id: number];
}>();
```

### Model Binding

```typescript
const modelValue = defineModel<string>();
// or
const agent = defineModel<Agent | null>();
```

## 12. Vue State Management - The Hierarchy of State

Understanding where state belongs is critical to writing clean, maintainable Vue components. State management follows a clear hierarchy from most preferred to least preferred.

### The State Hierarchy (Best to Worst)

| Priority | Location | When to Use |
|----------|----------|-------------|
| 1st | **Internal State** | Default. State that only this component needs |
| 2nd | **Composables** | State shared across multiple components |
| 3rd | **Props + Events** | Cross-domain communication where component doesn't know what to do |

### 1. Internal State (The Ideal)

**The perfect component manages 100% of its own state internally.**

A component that owns all its state is self-contained, testable, and easy to reason about. This should always be your default approach.

```vue
<script setup lang="ts">
// GOOD: Component owns its own UI state
const isExpanded = ref(false);
const searchQuery = ref('');
const selectedTab = ref('details');

// Toggle functions operate on internal state
function toggleExpanded() {
    isExpanded.value = !isExpanded.value;
}
</script>
```

**Ask yourself:** Does anything outside this component need to read or write this state?
- **No** → Keep it internal
- **Yes** → Move to a composable (not props/events!)

### 2. Composables (Shared State)

**When multiple components need the same state, use a composable. Import it everywhere it's needed.**

Composables provide a single source of truth that any component can read from or write to. No prop drilling. No event bubbling. Direct access.

```typescript
// useFragmentSelectorModes.ts
export function useFragmentSelectorModes() {
    const isEditModeActive = ref(false);
    const showProperties = ref(true);
    const showCodeSidebar = ref(false);

    function toggleShowProperties() {
        showProperties.value = !showProperties.value;
    }

    function toggleShowCode() {
        showCodeSidebar.value = !showCodeSidebar.value;
    }

    return {
        isEditModeActive,
        showProperties,
        showCodeSidebar,
        toggleShowProperties,
        toggleShowCode
    };
}
```

**Components import and use directly:**

```vue
<script setup lang="ts">
// Any component that needs this state just imports the composable
const modes = useFragmentSelectorModes();

// Direct access - no props, no events
function handleToggle() {
    modes.toggleShowCode();  // Updates everywhere automatically
}
</script>
```

**Benefits:**
- Single source of truth
- Any component can read/write
- Changes reflect everywhere instantly
- No prop drilling through intermediate components
- No event bubbling up chains

### 3. Props + Events (Cross-Domain Only)

**Events are for when a component genuinely doesn't know what to do.**

A generic `<Button>` component emits `click` because it has no idea what clicking it should accomplish - that's the parent's domain. The button's responsibility ends at "I was clicked."

```vue
<!-- GOOD: Generic button doesn't know what click means -->
<template>
    <button @click="emit('click')">
        <slot />
    </button>
</template>
```

**But domain-specific components DO know what to do:**

A `FragmentSelectorProperty` component with a delete button knows exactly what deleting means - remove this property from the schema. It should NOT emit an event and wait for a parent to do the work.

```vue
<!-- BAD: Emitting when we know exactly what to do -->
<template>
    <button @click="emit('delete', property.name)">Delete</button>
</template>

<!-- GOOD: Component handles its own domain logic -->
<script setup lang="ts">
const editor = useFragmentSchemaEditor();

function handleDelete() {
    editor.removeProperty(props.modelPath, props.property.name);
}
</script>

<template>
    <button @click="handleDelete">Delete</button>
</template>
```

### Decision Framework

Use this flowchart to determine where state belongs:

```
Does this state need to be accessed outside this component?
│
├─ NO → Internal State (ref/reactive in setup)
│
└─ YES → Do multiple components need to read/write it?
         │
         ├─ YES → Composable (shared state)
         │
         └─ NO → Does this component know what to do with the data?
                  │
                  ├─ YES → Composable (component calls method directly)
                  │
                  └─ NO → Props/Events (cross-domain communication)
```

### Anti-Patterns to Avoid

#### Anti-Pattern 1: Prop Drilling

**BAD:** Passing state through multiple component layers

```vue
<!-- Parent passes to Child1, Child1 passes to Child2, Child2 passes to Child3... -->
<Child1 :mode="mode" @update:mode="mode = $event" />
```

**GOOD:** Child components import the composable directly

```vue
<!-- Each component that needs mode just imports it -->
<script setup>
const { mode } = useAppMode();
</script>
```

#### Anti-Pattern 2: Event Bubbling

**BAD:** Events bubbling up through multiple layers

```vue
<!-- GrandChild emits to Child, Child re-emits to Parent, Parent re-emits to GrandParent... -->
<GrandChild @delete="emit('delete', $event)" />
```

**GOOD:** GrandChild calls the composable method directly

```vue
<script setup>
const editor = useSchemaEditor();
const handleDelete = () => editor.deleteItem(props.item);
</script>
```

#### Anti-Pattern 3: Two-Way Binding for Shared State

**BAD:** Using v-model for state that should be in a composable

```vue
<ControlPanel
    v-model:is-edit-mode="isEditMode"
    v-model:show-properties="showProperties"
    v-model:show-code="showCode"
/>
```

**GOOD:** Pass the composable, let child access/modify directly

```vue
<ControlPanel :modes="modes" />

<!-- Inside ControlPanel -->
<script setup>
const props = defineProps<{ modes: FragmentSelectorModesResult }>();
// Direct access: props.modes.toggleShowCode()
</script>
```

### When Events ARE Appropriate

Events are the right choice for:

1. **Generic/reusable components** - Buttons, inputs, dialogs that don't know their context
2. **Navigation requests** - "Open this panel", "Navigate to this route"
3. **Cross-boundary communication** - Between unrelated feature domains
4. **Library components** - Components designed for reuse across projects

```vue
<!-- Generic ActionButton - doesn't know what action means -->
<ActionButton type="delete" @click="handleDelete" />

<!-- Generic Dialog - doesn't know what confirm means -->
<ConfirmDialog @confirm="doSomething" @cancel="close" />
```

### Summary

| State Type | Location | Example |
|------------|----------|---------|
| UI state (expanded, selected tab) | Internal `ref()` | `const isExpanded = ref(false)` |
| Shared feature state | Composable | `const modes = useFragmentSelectorModes()` |
| Domain actions | Composable methods | `editor.deleteProperty(path, name)` |
| Generic component callbacks | Events | `<Button @click="...">` |

**The goal:** Components that know their domain should act on it directly. Only truly generic components should defer to their parents via events.

## 13. Direct Modification vs Emits Pattern

**Use `dxController.getAction()` for direct resource modifications when the component has the resource object available.**

When a component directly has a resource object (from props or computed), use the controller's action directly instead of emitting events. This keeps the modification logic encapsulated in the component.

**When to use emits:**
- Navigation events (view, edit, show-dialog)
- Events that require parent coordination
- Events where the parent has context the child doesn't

**When to use dxController directly:**
- The component has the resource object directly available
- The component is modifying the resource itself (update, delete)

**Example:**

```typescript
const updateAction = dxArtifact.getAction("update");
await updateAction.trigger(props.artifact, { text_content: newValue });
```

## Testing & Build

### Build Process

- **NEVER run yarn type-check** - Type checking is handled by the IDE
- Use `yarn build` for production builds and validation
- Prefer manual code review over automated type checking

**🚨 NEVER build quasar-ui-danx directly**
- ❌ **WRONG**: `cd /path/to/quasar-ui-danx/ui && yarn build`
- ✅ **CORRECT**: Just run `yarn build` in the spa/ directory
- The projects are locally linked via symlinks
- Building quasar-ui-danx directly causes issues with the local linking
- SPA build automatically picks up changes from quasar-ui-danx source files

## Key Principles

1. **Use existing danx components** - Don't reinvent the wheel
2. **Controller pattern for CRUD** - Use DanxController for consistency
3. **storeObjects for state** - Automatic reactivity across components
4. **Semantic action types** - Use predefined action button types
5. **Tailwind utilities** - Avoid inline styles
6. **TypeScript everything** - Full type safety
7. **Modular organization** - Keep related code together
8. **No automated type checking** - NEVER run yarn type-check commands
