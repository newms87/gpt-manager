import { computed, ref } from "vue";

export interface AssistantContextData {
    context: string;
    object?: any;
    metadata?: Record<string, any>;
}

export interface ContextProvider {
    id: string;
    priority: number; // Higher priority wins when multiple contexts are active
    data: AssistantContextData;
}

// Global state outside of composable function to ensure singleton behavior
const activeContextProviders = ref<Map<string, ContextProvider>>(new Map());

export function useAssistantGlobalContext() {
    // Computed to get the highest priority active context
    const activeContext = computed<AssistantContextData | null>(() => {
        if (activeContextProviders.value.size === 0) {
            return {
                context: "general-chat"
            };
        }

        // Get all providers and sort by priority (descending)
        const providers = Array.from(activeContextProviders.value.values());
        providers.sort((a, b) => b.priority - a.priority);

        // Return the highest priority context
        return providers[0]?.data || null;
    });

    // Current context values
    const currentContext = computed(() => activeContext.value?.context || "general-chat");
    const currentObject = computed(() => activeContext.value?.object || null);
    const currentMetadata = computed(() => activeContext.value?.metadata || {});

    // Context display names
    const contextDisplayNames: Record<string, string> = {
        "schema-editor": "Schema Editor",
        "workflow-editor": "Workflow Editor",
        "agent-management": "Agent Management",
        "task-management": "Task Management",
        "general-chat": "General Chat"
    };

    const contextDisplayName = computed(() => {
        return contextDisplayNames[currentContext.value] || "AI Assistant";
    });

    // Register a context provider
    function registerContextProvider(id: string, priority: number, data: AssistantContextData): void {
        activeContextProviders.value.set(id, {
            id,
            priority,
            data
        });
    }

    // Unregister a context provider
    function unregisterContextProvider(id: string): void {
        activeContextProviders.value.delete(id);
    }

    // Update context data for an existing provider
    function updateContextProvider(id: string, data: Partial<AssistantContextData>): void {
        const provider = activeContextProviders.value.get(id);
        if (provider) {
            provider.data = { ...provider.data, ...data };
            // Trigger reactivity by creating a new Map
            activeContextProviders.value = new Map(activeContextProviders.value);
        }
    }

    // Check if a specific context is active
    function isContextActive(context: string): boolean {
        return currentContext.value === context;
    }

    // Get all active contexts (useful for debugging)
    function getActiveContexts(): ContextProvider[] {
        return Array.from(activeContextProviders.value.values());
    }

    return {
        // Current context state
        currentContext,
        currentObject,
        currentMetadata,
        contextDisplayName,

        // Context management
        registerContextProvider,
        unregisterContextProvider,
        updateContextProvider,

        // Helpers
        isContextActive,
        getActiveContexts,

        // Constants
        contextDisplayNames
    };
}

// Export a singleton instance for components that just want to read the context
export const assistantGlobalContext = useAssistantGlobalContext();
