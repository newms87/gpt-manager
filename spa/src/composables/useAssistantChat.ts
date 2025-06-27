import { computed, nextTick, onMounted, onUnmounted, ref } from "vue";
import { request, storeObjects } from "quasar-ui-danx";
import { usePusher } from "@/helpers/pusher";
import { useAssistantDebug } from "@/composables/useAssistantDebug";
import { useAssistantGlobalContext } from "@/composables/useAssistantGlobalContext";
// Removed useAssistantState - using thread actions directly
import { AssistantThread, AssistantMessage } from "@/components/Modules/Assistant/types";

const STORAGE_KEY = 'assistant-thread-id';

// Global state - single source of truth
const isLoading = ref(false);
const storedThreadId = ref<number | null>(null);
const storedThreads = ref<AssistantThread[]>([]);
const localErrorMessages = ref<Array<{ id: string; role: 'error'; content: string; error_data: any; created_at: string }>>([]);
const hasLoadedFromStorage = ref(false);

// Global computed properties
const currentThread = computed(() => {
    if (!storedThreadId.value) return null;
    return storedThreads.value.find(t => t.id === storedThreadId.value) || null;
});

const messages = computed(() => {
    const threadMessages = currentThread.value?.messages || [];
    const allMessages = [...threadMessages, ...localErrorMessages.value];
    
    return allMessages.sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime());
});

const isThreadRunning = computed(() => {
    return currentThread.value?.is_running || false;
});

export function useAssistantChat() {
    const pusher = usePusher();
    const { currentContext, currentObject } = useAssistantGlobalContext();
    
    const {
        debugSendMessage,
        debugChatResponse,
        debugThreadStored,
        debugWebSocketSubscribe,
        debugWebSocketUpdate,
        debugThreadLoaded,
        debugThreadCleared,
        debugStorageCheck,
        debugError,
        debugLog
    } = useAssistantDebug();

    // Build context data
    const contextData = computed(() => {
        const contextResources = [];
        
        if (currentObject.value?.id && currentObject.value?.__type) {
            contextResources.push({
                resource_type: currentObject.value.__type,
                resource_id: currentObject.value.id
            });
        }
        
        const metadata = {
            page_url: window.location.href,
            page_path: window.location.pathname,
            user_agent: navigator.userAgent,
            viewport_width: window.innerWidth,
            viewport_height: window.innerHeight,
            timestamp: new Date().toISOString()
        };
        
        return {
            resources: contextResources,
            metadata
        };
    });

    function addErrorMessage(error: any): void {
        let parsedError = error;
        let errorContent = 'An error occurred while processing your message';
        
        if (error?.response?.data) {
            parsedError = error.response.data;
            if (parsedError.message) {
                errorContent = parsedError.message;
            }
        } else if (error?.message) {
            errorContent = error.message;
            parsedError = {
                message: error.message,
                status: error?.response?.status,
                url: error?.config?.url,
                stack: error?.stack
            };
        }
        
        const errorMessage = {
            id: `error-${Date.now()}-${Math.random()}`,
            role: 'error' as const,
            content: errorContent,
            error_data: parsedError,
            created_at: new Date().toISOString(),
        };
        
        localErrorMessages.value.push(errorMessage);
    }

    async function sendMessage(message: string): Promise<void> {
        if (!message.trim() || isLoading.value || isThreadRunning.value) return;
        
        debugSendMessage(message);
        isLoading.value = true;
        
        try {
            let response;
            
            if (currentThread.value?.id) {
                // Continue existing chat - use existing thread API
                response = await request.post(`assistant/threads/${currentThread.value.id}/chat`, {
                    message,
                    context: currentContext.value,
                    context_data: contextData.value,
                });
            } else {
                // Start new chat
                response = await request.post('assistant/start-chat', {
                    message,
                    context: currentContext.value,
                    context_data: contextData.value,
                });
            }
            
            debugChatResponse(response);
            
            // Store the thread (response is now the thread directly)
            storedThreads.value = storeObjects([response]);
            const thread = storedThreads.value[0];
            
            debugThreadStored(thread.id);
            storedThreadId.value = thread.id;
            localStorage.setItem(STORAGE_KEY, thread.id.toString());
            
            // Actions are handled directly through thread.actions - no separate state needed
            
            subscribeToThread(thread);
            localErrorMessages.value = [];
            
        } catch (error) {
            debugError('to send message', error);
            addErrorMessage(error);
        } finally {
            isLoading.value = false;
        }
    }

    function startNewChat(): void {
        debugThreadCleared();
        
        localStorage.removeItem(STORAGE_KEY);
        storedThreadId.value = null;
        storedThreads.value = [];
        localErrorMessages.value = [];
        
        // Actions are cleared automatically when thread is cleared
        
        unsubscribeFromThread();
    }

    async function loadStoredThread(): Promise<void> {
        // Prevent duplicate loading
        if (hasLoadedFromStorage.value) {
            return;
        }
        hasLoadedFromStorage.value = true;
        
        const savedThreadId = localStorage.getItem(STORAGE_KEY);
        debugStorageCheck(savedThreadId);
        
        if (savedThreadId) {
            try {
                const response = await request.get(`threads/${savedThreadId}/details?fields[actions]=true`);
                debugThreadLoaded(response);
                storedThreads.value = storeObjects([response]);
                const thread = storedThreads.value[0];
                storedThreadId.value = thread.id;
                
                // Actions are handled directly through thread.actions - no separate state needed
                
                subscribeToThread(thread);
            } catch (error) {
                debugError('to load stored thread', error);
                localStorage.removeItem(STORAGE_KEY);
            }
        }
    }

    function subscribeToThread(thread: AssistantThread): void {
        if (!pusher) return;
        
        debugWebSocketSubscribe(thread.id);
        
        pusher.onEvent('AgentThread', 'updated', async (minimalThread: any) => {
            debugWebSocketUpdate(minimalThread.id, minimalThread);
            
            if (minimalThread.id === thread.id) {
                const existingThreadIndex = storedThreads.value.findIndex(t => t.id === minimalThread.id);
                if (existingThreadIndex >= 0) {
                    storedThreads.value = storedThreads.value.map(t => 
                        t.id === minimalThread.id ? { ...t, ...minimalThread } : t
                    );
                }
                
                if (!minimalThread.is_running) {
                    try {
                        const response = await request.get(`threads/${minimalThread.id}/details?fields[actions]=true`);
                        debugLog('WEBSOCKET', 'Fetched full thread data after completion');
                        storedThreads.value = storeObjects([response]);
                        
                        // Actions are handled directly through thread.actions - no separate state needed
                    } catch (error) {
                        debugError('fetching full thread data', error);
                    }
                }
            }
        });
    }

    function unsubscribeFromThread(): void {
        // Pusher cleanup is handled automatically by the library
    }

    // Don't auto-load - let components decide when to load

    return {
        // State
        isLoading,
        currentThread,
        messages,
        isThreadRunning,
        contextData,
        
        // Methods
        sendMessage,
        startNewChat,
        addErrorMessage,
        loadStoredThread,
        
        // Action methods - using ActionController patterns
        async approveAction(action: any) {
            try {
                await request.post(`assistant/actions/${action.id}`, { status: 'approved' });
                // Thread will be updated via websocket
            } catch (error) {
                debugError('approving action', error);
            }
        },
        
        async cancelAction(action: any) {
            try {
                await request.post(`assistant/actions/${action.id}`, { status: 'cancelled' });
                // Thread will be updated via websocket
            } catch (error) {
                debugError('cancelling action', error);
            }
        }
    };
}