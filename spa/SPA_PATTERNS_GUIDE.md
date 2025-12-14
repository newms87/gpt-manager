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

## 7. Styling Patterns

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

## 8. Common Composables

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

## 9. Formatting Functions

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

## 10. TypeScript Patterns

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

## 11. Direct Modification vs Emits Pattern

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
- Use `yarn build` only for production builds
- Prefer manual code review over automated type checking

## Key Principles

1. **Use existing danx components** - Don't reinvent the wheel
2. **Controller pattern for CRUD** - Use DanxController for consistency
3. **storeObjects for state** - Automatic reactivity across components
4. **Semantic action types** - Use predefined action button types
5. **Tailwind utilities** - Avoid inline styles
6. **TypeScript everything** - Full type safety
7. **Modular organization** - Keep related code together
8. **No automated type checking** - NEVER run yarn type-check commands
