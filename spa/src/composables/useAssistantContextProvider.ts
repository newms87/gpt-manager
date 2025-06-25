import { onMounted, onUnmounted, watch, Ref, unref } from "vue";
import { useAssistantGlobalContext } from "./useAssistantGlobalContext";

export interface ContextProviderOptions {
    context: string;
    priority?: number;
    object?: Ref<any> | any;
    metadata?: Ref<Record<string, any>> | Record<string, any>;
    autoRegister?: boolean;
}

export function useAssistantContextProvider(options: ContextProviderOptions) {
    const { 
        registerContextProvider, 
        unregisterContextProvider, 
        updateContextProvider 
    } = useAssistantGlobalContext();
    
    // Generate unique ID for this provider instance
    const providerId = `provider-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    
    // Default priority based on context type
    const defaultPriorities: Record<string, number> = {
        'schema-editor': 100,
        'workflow-editor': 100,
        'agent-management': 90,
        'task-management': 90,
        'general-chat': 0,
    };
    
    const priority = options.priority ?? defaultPriorities[options.context] ?? 50;
    
    // Register the context provider
    function register() {
        registerContextProvider(providerId, priority, {
            context: options.context,
            object: unref(options.object),
            metadata: unref(options.metadata) || {}
        });
    }
    
    // Unregister the context provider
    function unregister() {
        unregisterContextProvider(providerId);
    }
    
    // Update the context data
    function update(data: {
        object?: any;
        metadata?: Record<string, any>;
    }) {
        updateContextProvider(providerId, data);
    }
    
    // Watch for changes in reactive references
    if (options.object && typeof options.object === 'object' && 'value' in options.object) {
        watch(() => options.object, (newValue) => {
            update({ object: unref(newValue) });
        }, { deep: true });
    }
    
    if (options.metadata && typeof options.metadata === 'object' && 'value' in options.metadata) {
        watch(() => options.metadata, (newValue) => {
            update({ metadata: unref(newValue) || {} });
        }, { deep: true });
    }
    
    // Auto-register on mount and unregister on unmount
    if (options.autoRegister !== false) {
        onMounted(() => {
            register();
        });
        
        onUnmounted(() => {
            unregister();
        });
    }
    
    return {
        providerId,
        register,
        unregister,
        update,
    };
}