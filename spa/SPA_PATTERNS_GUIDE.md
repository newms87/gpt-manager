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
    type="create"      // Types: create, edit, delete, trash, merge, etc.
    color="blue-invert"
    tooltip="Create New"
    :loading="action.isApplying"
    @click="action.trigger()"
/>
```

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
    title="Delete Item?"
    message="This action cannot be undone."
    @confirm="deleteItem"
/>
```

**InfoDialog** - Information dialogs
```vue
<InfoDialog
    title="Process Details"
    :content="processInfo"
/>
```

### Utilities

**ListControlsPagination** - Pagination controls
```vue
<ListControlsPagination
    v-model:page="currentPage"
    :total="totalItems"
    :per-page="itemsPerPage"
/>
```

## 2. State Management with storeObjects

```typescript
import { storeObjects, storeObject, getItem, setItem } from "quasar-ui-danx";

// Store multiple objects (auto-normalizes and makes reactive)
const items = storeObjects(await api.list());

// Store single object (updates everywhere it's used)
storeObject(updatedItem);

// Local storage helpers
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
<EditIcon class="w-3" />  // Use Tailwind width classes
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
<QBtn :loading="action.isApplying" @click="action.trigger(object, data)">
    Save
</QBtn>
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

```typescript
import { fDate, fDateTime, fNumber, fCurrency, fPercent } from "quasar-ui-danx";

fDate(date);              // "Jan 1, 2024"
fDateTime(date);          // "Jan 1, 2024 3:45 PM"
fNumber(1234.56);         // "1,234.56"
fCurrency(1234.56);       // "$1,234.56"
fPercent(0.85);           // "85%"
```

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

## Key Principles

1. **Use existing danx components** - Don't reinvent the wheel
2. **Controller pattern for CRUD** - Use DanxController for consistency
3. **storeObjects for state** - Automatic reactivity across components
4. **Semantic action types** - Use predefined action button types
5. **Tailwind utilities** - Avoid inline styles
6. **TypeScript everything** - Full type safety
7. **Modular organization** - Keep related code together