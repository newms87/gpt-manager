// Removed useAssistantState - using thread actions directly
import { apiUrls } from "@/api";
import { AssistantThread } from "@/components/Modules/Assistant/types";
import { useAssistantGlobalContext } from "@/composables/useAssistantGlobalContext";
import { usePusher } from "@/helpers/pusher";
import { request, storeObjects } from "quasar-ui-danx";
import { computed, ref } from "vue";

const STORAGE_KEY = "assistant-thread-id";

// Global state - single source of truth
const isLoading = ref(false);
const storedThreadId = ref<number | null>(null);
const storedThreads = ref<AssistantThread[]>([]);
const localErrorMessages = ref<Array<{
    id: string;
    role: "error";
    content: string;
    error_data: any;
    created_at: string
}>>([]);
const hasLoadedFromStorage = ref(false);
const subscribedThreadId = ref<number | null>(null);

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
        let errorContent = "An error occurred while processing your message";

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
            role: "error" as const,
            content: errorContent,
            error_data: parsedError,
            created_at: new Date().toISOString()
        };

        localErrorMessages.value.push(errorMessage);
    }

    async function sendMessage(message: string): Promise<void> {
        if (!message.trim() || isLoading.value || isThreadRunning.value) return;

        isLoading.value = true;

        try {
            let response;

            if (currentThread.value?.id) {
                // Continue existing chat - use existing thread API
                response = await request.post(apiUrls.assistant.threadChat({ threadId: currentThread.value.id }), {
                    message,
                    context: currentContext.value,
                    context_data: contextData.value
                });
            } else {
                // Start new chat
                response = await request.post(apiUrls.assistant.startChat, {
                    message,
                    context: currentContext.value,
                    context_data: contextData.value
                });
            }


            // Store the thread (response is now the thread directly)
            storedThreads.value = storeObjects([response]);
            const thread = storedThreads.value[0];

            storedThreadId.value = thread.id;
            localStorage.setItem(STORAGE_KEY, thread.id.toString());

            // Actions are handled directly through thread.actions - no separate state needed

            subscribeToThread(thread);
            localErrorMessages.value = [];

        } catch (error) {
            addErrorMessage(error);
        } finally {
            isLoading.value = false;
        }
    }

    async function startNewChat(): Promise<void> {

        localStorage.removeItem(STORAGE_KEY);
        storedThreadId.value = null;
        storedThreads.value = [];
        localErrorMessages.value = [];

        // Actions are cleared automatically when thread is cleared

        await unsubscribeFromThread();
    }

    async function loadStoredThread(): Promise<void> {
        // Prevent duplicate loading
        if (hasLoadedFromStorage.value) {
            return;
        }
        hasLoadedFromStorage.value = true;

        const savedThreadId = localStorage.getItem(STORAGE_KEY);

        if (savedThreadId) {
            try {
                const response = await request.get(apiUrls.assistant.threadDetails({ threadId: parseInt(savedThreadId) }) + '?fields[actions]=true');
                storedThreads.value = storeObjects([response]);
                const thread = storedThreads.value[0];
                storedThreadId.value = thread.id;

                // Actions are handled directly through thread.actions - no separate state needed

                subscribeToThread(thread);
            } catch (error) {
                localStorage.removeItem(STORAGE_KEY);
            }
        }
    }

    async function subscribeToThread(thread: AssistantThread): Promise<void> {
        if (!pusher) return;

        // Unsubscribe from previous thread if any
        await unsubscribeFromThread();

        // Subscribe to the new thread
        try {
            await pusher.subscribeToModel("AgentThread", ["updated"], thread.id);
            subscribedThreadId.value = thread.id;

            // Set up event handler for updates
            pusher.onEvent("AgentThread", "updated", async (minimalThread: any) => {
                if (minimalThread.id === thread.id) {
                    const existingThreadIndex = storedThreads.value.findIndex(t => t.id === minimalThread.id);
                    if (existingThreadIndex >= 0) {
                        storedThreads.value = storedThreads.value.map(t =>
                            t.id === minimalThread.id ? { ...t, ...minimalThread } : t
                        );
                    }

                    if (!minimalThread.is_running) {
                        try {
                            const response = await request.get(apiUrls.assistant.threadDetails({ threadId: minimalThread.id }) + '?fields[actions]=true');
                            storedThreads.value = storeObjects([response]);

                            // Actions are handled directly through thread.actions - no separate state needed
                        } catch (error) {
                        }
                    }
                }
            });
        } catch (error) {
            console.error("Failed to subscribe to thread:", error);
        }
    }

    async function unsubscribeFromThread(): Promise<void> {
        if (!pusher || subscribedThreadId.value === null) return;

        try {
            await pusher.unsubscribeFromModel("AgentThread", ["updated"], subscribedThreadId.value);
            subscribedThreadId.value = null;
        } catch (error) {
            console.error("Failed to unsubscribe from thread:", error);
        }
    }

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
                await request.post(apiUrls.assistant.action({ actionId: action.id }), { status: "approved" });
                // Thread will be updated via websocket
            } catch (error) {
            }
        },

        async cancelAction(action: any) {
            try {
                await request.post(apiUrls.assistant.action({ actionId: action.id }), { status: "cancelled" });
                // Thread will be updated via websocket
            } catch (error) {
            }
        }
    };
}
