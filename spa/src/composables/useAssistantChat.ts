import { computed, nextTick, onMounted, onUnmounted, ref } from "vue";
import { request, storeObjects } from "quasar-ui-danx";
import { usePusher } from "@/helpers/pusher";
import { useAssistantDebug } from "@/composables/useAssistantDebug";
import { AssistantThread, AssistantMessage } from "@/components/Modules/Assistant/types";

const STORAGE_KEY = 'assistant-thread-id';

export function useAssistantChat(context: string, contextData: Record<string, any> = {}) {
    // State
    const isLoading = ref(false);
    const storedThreadId = ref<number | null>(null);
    const storedThreads = ref<AssistantThread[]>([]);
    const localErrorMessages = ref<Array<{ id: string; role: 'error'; content: string; error_data: any; created_at: string }>>([]);

    // WebSocket
    const pusher = usePusher();

    // Debug utilities
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

    // Computed properties
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

    // Methods
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
            const response = await request.post('assistant/chat', {
                message,
                context,
                context_data: contextData,
                thread_id: currentThread.value?.id,
            });
            
            debugChatResponse(response);
            
            if (response.thread) {
                storedThreads.value = storeObjects([response.thread]);
                const thread = storedThreads.value[0];
                
                debugThreadStored(thread.id);
                storedThreadId.value = thread.id;
                localStorage.setItem(STORAGE_KEY, thread.id.toString());
                
                subscribeToThread(thread);
            }
            
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
        
        unsubscribeFromThread();
    }

    async function loadStoredThread(): Promise<void> {
        const savedThreadId = localStorage.getItem(STORAGE_KEY);
        debugStorageCheck(savedThreadId);
        
        if (savedThreadId) {
            try {
                const response = await request.get(`assistant/threads/${savedThreadId}`);
                if (response.thread) {
                    debugThreadLoaded(response.thread);
                    storedThreads.value = storeObjects([response.thread]);
                    const thread = storedThreads.value[0];
                    storedThreadId.value = thread.id;
                    subscribeToThread(thread);
                }
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
                        const response = await request.get(`assistant/threads/${minimalThread.id}`);
                        if (response.thread) {
                            debugLog('WEBSOCKET', 'Fetched full thread data after completion');
                            storedThreads.value = storeObjects([response.thread]);
                        }
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

    // Lifecycle
    onMounted(async () => {
        await loadStoredThread();
    });

    onUnmounted(() => {
        unsubscribeFromThread();
    });

    return {
        // State
        isLoading,
        currentThread,
        messages,
        isThreadRunning,
        
        // Methods
        sendMessage,
        startNewChat,
        addErrorMessage,
    };
}