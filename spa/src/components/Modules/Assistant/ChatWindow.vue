<template>
    <div class="chat-window-wrapper fixed bottom-4 right-4 z-50">
        <div
            class="chat-window bg-white rounded-lg shadow-2xl border border-gray-200 w-[600px] h-[85vh] flex flex-col overflow-hidden"
            :class="animationClass"
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
                    v-if="minimizable"
                    class="text-blue-200 hover:text-white transition-colors"
                    @click="handleMinimize"
                >
                    <FaSolidMinus class="w-4 h-4" />
                </button>
                <button
                    class="text-blue-200 hover:text-white transition-colors"
                    @click="handleClose"
                >
                    <FaSolidX class="w-4 h-4" />
                </button>
            </div>
        </div>

        <!-- Loading State -->
        <div 
            v-if="isLoading"
            class="flex-1 flex flex-col items-center justify-center p-8"
        >
            <div class="loading-spinner mb-4">
                <FaSolidSpinner class="w-8 h-8 text-blue-600 animate-spin" />
            </div>
            <div class="text-gray-600 text-center">
                <div class="font-medium mb-1">Loading Chat</div>
                <div class="text-sm">Preparing your conversation...</div>
            </div>
        </div>

        <!-- Chat Content -->
        <div v-else class="flex-1 flex flex-col min-h-0">
            <AssistantChat />
        </div>

        <!-- Action Panel -->
        <AssistantActionPanel
            v-if="hasActiveActions && !isLoading"
            :thread-id="currentThread?.id"
            class="border-t border-gray-200"
        />
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { FaSolidRobot, FaSolidMinus, FaSolidX, FaSolidSpinner } from "danx-icon";
import { useAssistantChat } from "@/composables/useAssistantChat";
import { useAssistantGlobalContext } from "@/composables/useAssistantGlobalContext";
import AssistantChat from "./AssistantChat.vue";
import AssistantActionPanel from "./AssistantActionPanel.vue";
import CapabilitiesButton from "./CapabilitiesButton.vue";

// Props
interface Props {
    minimizable?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    minimizable: true,
});

// Emits
interface Emits {
    (e: 'close'): void;
    (e: 'minimize'): void;
}

const emit = defineEmits<Emits>();

// State
const isLoading = ref(true);
const animationClass = ref('animate-slide-up');

// Composables
const { contextDisplayName } = useAssistantGlobalContext();
const { currentThread, loadStoredThread } = useAssistantChat();

// Computed
const hasActiveActions = computed(() =>
    (currentThread.value?.actions || []).some(action =>
        action.is_pending || action.is_in_progress
    )
);

// Methods
function handleClose(): void {
    emit('close');
}

function handleMinimize(): void {
    emit('minimize');
}

async function initializeChat(): Promise<void> {
    try {
        // Load any stored thread
        await loadStoredThread();
    } catch (error) {
        console.error('Failed to initialize chat:', error);
    } finally {
        isLoading.value = false;
    }
}

// Lifecycle
onMounted(() => {
    initializeChat();
});
</script>

<style lang="scss" scoped>
.chat-window {
    position: relative;

    &.animate-slide-up {
        animation: slideUp 0.3s ease-out;
    }

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

.loading-spinner {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

@media (max-width: 768px) {
    .chat-window-wrapper {
        bottom: 0.5rem;
        right: 0.5rem;
        
        .chat-window {
            width: calc(100vw - 1rem);
            height: calc(100vh - 1rem);
        }
    }
}
</style>