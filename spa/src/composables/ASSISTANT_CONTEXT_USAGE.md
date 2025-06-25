# Assistant Context Provider Usage Guide

This guide shows how to register your component as a context provider for the AI Assistant.

## Basic Usage

```typescript
import { useAssistantContextProvider } from "@/composables/useAssistantContextProvider";

// In your component's setup:
const myObject = ref({ id: 1, name: 'Example' });

useAssistantContextProvider({
    context: 'my-context',
    object: myObject,
    metadata: {
        someProperty: 'value'
    }
});
```

## Example: Workflow Editor

```vue
<script setup lang="ts">
import { useAssistantContextProvider } from "@/composables/useAssistantContextProvider";
import { toRef, computed } from "vue";

const props = defineProps<{
    workflowDefinition?: WorkflowDefinition;
}>();

// Register as context provider
const workflowRef = toRef(props, 'workflowDefinition');
const contextMetadata = computed(() => ({
    workflowId: props.workflowDefinition?.id,
    workflowName: props.workflowDefinition?.name,
    nodeCount: props.workflowDefinition?.nodes?.length || 0,
    isActive: props.workflowDefinition?.is_active
}));

useAssistantContextProvider({
    context: 'workflow-editor',
    priority: 100,
    object: workflowRef,
    metadata: contextMetadata
});
</script>
```

## Context Types

- `schema-editor` - JSON Schema Editor
- `workflow-editor` - Workflow Editor
- `agent-management` - Agent Configuration
- `task-management` - Task Management
- `general-chat` - Default context

## Priority Levels

- 100+ - Active editors (schema, workflow)
- 90 - Management screens (agents, tasks)
- 50 - Default for custom contexts
- 0 - General chat

## Advanced Usage

### Manual Registration

```typescript
const { register, unregister, update } = useAssistantContextProvider({
    context: 'my-context',
    object: myObject,
    autoRegister: false // Don't auto-register
});

// Register manually when needed
onSomeEvent(() => {
    register();
});

// Update context data
watch(myObject, (newValue) => {
    update({ object: newValue });
});

// Cleanup when done
onCleanup(() => {
    unregister();
});
```

### Multiple Contexts

If your component can provide multiple contexts:

```typescript
// Primary context
const schemaProvider = useAssistantContextProvider({
    context: 'schema-editor',
    object: schemaDefinition,
    priority: 100
});

// Secondary context (lower priority)
const dataProvider = useAssistantContextProvider({
    context: 'data-preview',
    object: previewData,
    priority: 50
});
```

The AI Assistant will use the highest priority context when multiple are active.