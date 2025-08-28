import { ref, computed } from "vue";
import { request, storeObject, storeObjects } from "quasar-ui-danx";
import { AssistantAction, AssistantCapability, CapabilitiesResponse } from "@/components/Modules/Assistant/types";

// Global state for actions
const activeActions = ref<AssistantAction[]>([]);

// Global state for capabilities
const contextCapabilities = ref<AssistantCapability[]>([]);
const capabilitiesLoaded = ref(false);
const isLoadingCapabilities = ref(false);

export function useAssistantState() {

    // Add actions from chat response
    function handleChatActions(actions: AssistantAction[]) {
        if (!actions || actions.length === 0) return;
        
        const storedActions = storeObjects(actions);
        activeActions.value = [...activeActions.value, ...storedActions];
    }

    // Get action by ID
    function getActionById(actionId: number | string): AssistantAction | undefined {
        const id = typeof actionId === 'string' ? parseInt(actionId) : actionId;
        return activeActions.value.find(a => a.id === id);
    }

    // Action management
    async function approveAction(actionId: number): Promise<void> {
        
        try {
            const response = await request.post(`assistant/actions/${actionId}/approve`);
            const updatedAction = storeObject(response);
            
            // Update in our local array
            activeActions.value = activeActions.value.map(a => 
                a.id === actionId ? updatedAction : a
            );
            
            // Remove completed actions after delay
            if (updatedAction.is_finished) {
                setTimeout(() => {
                    activeActions.value = activeActions.value.filter(a => a.id !== actionId);
                }, 3000);
            }
        } catch (error) {
            throw error;
        }
    }

    async function cancelAction(actionId: number): Promise<void> {
        
        try {
            const response = await request.post(`assistant/actions/${actionId}/cancel`);
            const updatedAction = storeObject(response);
            
            // Update in our local array
            activeActions.value = activeActions.value.map(a => 
                a.id === actionId ? updatedAction : a
            );
            
            // Remove cancelled actions after delay
            setTimeout(() => {
                activeActions.value = activeActions.value.filter(a => a.id !== actionId);
            }, 3000);
        } catch (error) {
            throw error;
        }
    }

    async function cancelAllActions(): Promise<void> {
        
        const pendingActions = activeActions.value.filter(a => a.is_pending || a.is_in_progress);
        await Promise.all(pendingActions.map(action => cancelAction(action.id)));
    }

    // Clear all actions
    function clearActions(): void {
        activeActions.value = [];
    }

    // Capabilities management
    async function loadCapabilities(context: string): Promise<void> {
        if (capabilitiesLoaded.value || isLoadingCapabilities.value) return;
        
        isLoadingCapabilities.value = true;
        
        try {
            const response = await request.get<CapabilitiesResponse>(`assistant/capabilities/${context}`);
            
            // Transform the key-value response into capability objects
            contextCapabilities.value = Object.entries(response).map(([key, label]) => ({
                key,
                label,
                description: undefined,
                icon: undefined
            }));
            
            capabilitiesLoaded.value = true;
        } catch (error) {
            contextCapabilities.value = [];
        } finally {
            isLoadingCapabilities.value = false;
        }
    }

    function clearCapabilities(): void {
        contextCapabilities.value = [];
        capabilitiesLoaded.value = false;
    }

    // Computed properties
    const pendingActions = computed(() => 
        activeActions.value.filter(a => a.is_pending)
    );

    const hasActiveActions = computed(() => 
        activeActions.value.length > 0
    );

    const hasPendingActions = computed(() => 
        pendingActions.value.length > 0
    );

    return {
        // State
        activeActions,
        contextCapabilities,
        capabilitiesLoaded,
        isLoadingCapabilities,
        
        // Computed
        pendingActions,
        hasActiveActions,
        hasPendingActions,
        
        // Action methods
        handleChatActions,
        getActionById,
        approveAction,
        cancelAction,
        cancelAllActions,
        clearActions,
        
        // Capability methods
        loadCapabilities,
        clearCapabilities
    };
}