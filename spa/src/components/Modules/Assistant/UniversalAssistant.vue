<template>
    <div class="universal-assistant fixed bottom-4 right-4 z-50">
        <!-- Floating Chat Icon -->
        <div
            v-if="!isOpen"
            class="chat-trigger cursor-pointer bg-blue-600 hover:bg-blue-700 rounded-full p-4 shadow-lg transition-all duration-300 hover:scale-110"
            @click="toggleChat"
        >
            <FaSolidComments class="w-6 h-6 text-white" />
            <div
                v-if="hasUnreadMessages"
                class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 rounded-full flex items-center justify-center"
            >
                <span class="text-xs text-white font-bold">{{ unreadCount }}</span>
            </div>
        </div>

        <!-- Chat Panel -->
        <div
            v-show="isOpen"
            class="chat-panel bg-white rounded-lg shadow-2xl border border-gray-200 w-[480px] h-[700px] flex flex-col overflow-hidden"
        >
            <!-- Header -->
            <div class="chat-header bg-blue-600 text-white p-4 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <FaSolidRobot class="w-5 h-5" />
                    <div class="flex items-center space-x-2">
                        <span class="font-semibold">{{ contextDisplayName }}</span>
                        <CapabilitiesButton />
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <button
                        v-if="isMinimizable"
                        class="text-blue-200 hover:text-white transition-colors"
                        @click="minimizeChat"
                    >
                        <FaSolidMinus class="w-4 h-4" />
                    </button>
                    <button
                        class="text-blue-200 hover:text-white transition-colors"
                        @click="closeChat"
                    >
                        <FaSolidX class="w-4 h-4" />
                    </button>
                </div>
            </div>


            <!-- Chat Content -->
            <div class="flex-1 flex flex-col min-h-0">
                <AssistantChat
                    :thread="currentThread"
                    :context="currentContext"
                    :context-data="currentContextData"
                    :loading="chatLoading"
                    @message="handleMessage"
                    @thread-created="handleThreadCreated"
                />
            </div>

            <!-- Action Panel -->
            <AssistantActionPanel
                v-if="hasActiveActions"
                :actions="activeActions"
                :thread-id="currentThread?.id"
                class="border-t border-gray-200"
                @action-approved="handleActionApproved"
                @action-cancelled="handleActionCancelled"
            />
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { request, storeObject } from "quasar-ui-danx";
import { FaSolidComments, FaSolidMinus, FaSolidRobot, FaSolidX } from "danx-icon";
import AssistantChat from "./AssistantChat.vue";
import AssistantActionPanel from "./AssistantActionPanel.vue";
import CapabilitiesButton from "./CapabilitiesButton.vue";
import { useAssistantGlobalContext } from "@/composables/useAssistantGlobalContext";
import { useAssistantDebug } from "@/composables/useAssistantDebug";
import { AssistantAction, AssistantThread, AssistantMessage } from "./types";

// Props
interface Props {
    autoOpen?: boolean;
    persistent?: boolean;
    minimizable?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    autoOpen: false,
    persistent: false,
    minimizable: true,
});

// State
const isOpen = ref(false);
const { debugError, debugSendMessage, debugChatResponse, debugLog } = useAssistantDebug();
const isMinimized = ref(false);
const chatLoading = ref(false);
const currentThread = ref<AssistantThread | null>(null);
const activeActions = ref<AssistantAction[]>([]);
const unreadCount = ref(0);

// Use global context from components
const {
    currentContext,
    currentObject,
    currentMetadata,
    contextDisplayName,
} = useAssistantGlobalContext();

// Computed properties
const hasUnreadMessages = computed(() => unreadCount.value > 0);
const hasActiveActions = computed(() => activeActions.value.length > 0);
const isMinimizable = computed(() => props.minimizable && isOpen.value);

// Build context data to send to the backend
const currentContextData = computed(() => ({
    ...currentMetadata.value,
    objectId: currentObject.value?.id,
    objectType: currentObject.value?.constructor?.name || null
}));


// Methods
function toggleChat() {
    if (isMinimized.value) {
        debugLog('UI', 'Restoring assistant chat from minimized state');
        isMinimized.value = false;
    } else {
        isOpen.value = !isOpen.value;
        if (isOpen.value) {
            debugLog('UI', 'Opening assistant chat interface');
            onChatOpened();
        } else {
            debugLog('UI', 'Closing assistant chat interface');
        }
    }
}

function minimizeChat() {
    debugLog('UI', 'Minimizing assistant chat interface');
    isMinimized.value = true;
}

function closeChat() {
    debugLog('UI', 'Closing assistant chat interface');
    isOpen.value = false;
    isMinimized.value = false;
}

function onChatOpened() {
    unreadCount.value = 0;
}

async function handleMessage(message: string) {
    try {
        debugSendMessage(message);
        chatLoading.value = true;
        
        const response = await request.post('assistant/chat', {
            message,
            context: currentContext.value,
            context_data: currentContextData.value,
            thread_id: currentThread.value?.id,
        });

        debugChatResponse(response);

        if (response.thread) {
            storeObject(response.thread);
            currentThread.value = response.thread;
        }

        if (response.actions && response.actions.length > 0) {
            debugLog('UI', `Received ${response.actions.length} new actions from chat response`);
            // Store each action for reactivity
            response.actions.forEach(action => storeObject(action));
            activeActions.value = [...activeActions.value, ...response.actions];
        }

    } catch (error) {
        debugError('sending chat message', error);
    } finally {
        chatLoading.value = false;
    }
}

function handleThreadCreated(thread: AssistantThread) {
    currentThread.value = thread;
}

async function handleActionApproved(action: AssistantAction) {
    try {
        debugLog('UI', `Approving action: ${action.title} (ID: ${action.id})`);
        const updatedAction = await request.post(`assistant/actions/${action.id}/approve`);
        
        debugLog('SUCCESS', `Action approved: ${updatedAction.title} (Status: ${updatedAction.status})`);
        
        // Store the updated action for reactivity
        storeObject(updatedAction);
        
        // Update the action in our list
        const index = activeActions.value.findIndex(a => a.id === action.id);
        if (index !== -1) {
            activeActions.value[index] = updatedAction;
        }
        
        // Remove completed actions after a delay
        if (updatedAction.is_finished) {
            debugLog('UI', `Action finished, removing from list in 3s: ${updatedAction.title}`);
            setTimeout(() => {
                activeActions.value = activeActions.value.filter(a => a.id !== action.id);
            }, 3000);
        }
        
    } catch (error) {
        debugError('approving action', error);
    }
}

async function handleActionCancelled(action: AssistantAction) {
    try {
        debugLog('UI', `Cancelling action: ${action.title} (ID: ${action.id})`);
        const updatedAction = await request.post(`assistant/actions/${action.id}/cancel`);
        
        debugLog('SUCCESS', `Action cancelled: ${updatedAction.title} (Status: ${updatedAction.status})`);
        
        // Store the updated action for reactivity
        storeObject(updatedAction);
        
        // Update the action in our list to show it's cancelled
        const index = activeActions.value.findIndex(a => a.id === action.id);
        if (index !== -1) {
            activeActions.value[index] = updatedAction;
        }
        
        // Remove cancelled action after a delay
        debugLog('UI', `Cancelled action removing from list in 2s: ${updatedAction.title}`);
        setTimeout(() => {
            activeActions.value = activeActions.value.filter(a => a.id !== action.id);
        }, 2000);
        
    } catch (error) {
        debugError('cancelling action', error);
    }
}


// Lifecycle
onMounted(() => {
    if (props.autoOpen) {
        toggleChat();
    }
});

onUnmounted(() => {
    // Cleanup any subscriptions or timers
});

// Expose methods for external control
defineExpose({
    open: () => { isOpen.value = true; onChatOpened(); },
    close: closeChat,
    toggle: toggleChat,
    sendMessage: handleMessage,
});
</script>

<style lang="scss" scoped>
.universal-assistant {
    .chat-trigger {
        position: relative;
        
        &:hover {
            box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.5);
        }
    }

    .chat-panel {
        position: relative;
        animation: slideUp 0.3s ease-out;
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    }

    .context-capabilities {
        animation: fadeIn 0.2s ease-out;
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    }
}

@media (max-width: 480px) {
    .chat-panel {
        width: calc(100vw - 2rem);
        height: calc(100vh - 2rem);
        max-width: 400px;
        max-height: 600px;
    }
}
</style>