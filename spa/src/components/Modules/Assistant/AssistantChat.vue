<template>
    <div class="assistant-chat flex flex-col h-full">
        <!-- Messages Container -->
        <div
            ref="messagesContainer"
            class="messages-container flex-1 overflow-y-auto p-4 space-y-3"
        >
            <!-- Welcome Message -->
            <ChatWelcomeMessage
                v-if="isWelcomeVisible"
                @question-selected="handleSuggestedQuestion"
            />

            <!-- Chat Messages -->
            <ChatMessage
                v-for="message in messages"
                :key="message.id"
                :message="message"
            />

            <!-- Typing Indicator -->
            <div
                v-if="isLoading"
                class="typing-indicator bg-gray-100 rounded-lg px-4 py-2 max-w-[80%]"
            >
                <div class="flex items-center space-x-2">
                    <FaSolidRobot class="w-4 h-4 text-blue-500" />
                    <div class="flex space-x-1">
                        <div class="typing-dot bg-gray-400 rounded-full w-2 h-2 animate-bounce" style="animation-delay: 0ms" />
                        <div class="typing-dot bg-gray-400 rounded-full w-2 h-2 animate-bounce" style="animation-delay: 150ms" />
                        <div class="typing-dot bg-gray-400 rounded-full w-2 h-2 animate-bounce" style="animation-delay: 300ms" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Message Input -->
        <ChatInput ref="chatInput" />
    </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from "vue";
import { FaSolidRobot } from "danx-icon";
import { useAssistantChat } from "@/composables/useAssistantChat";
import { AssistantThread } from "./types";
import ChatWelcomeMessage from "./ChatWelcomeMessage.vue";
import ChatMessage from "./ChatMessage.vue";
import ChatInput from "./ChatInput.vue";

// Composable for chat logic - single source of truth
const {
    isLoading,
    currentThread,
    messages,
    isThreadRunning,
    sendMessage,
    startNewChat,
} = useAssistantChat();

// Template refs
const messagesContainer = ref<HTMLElement>();
const chatInput = ref<InstanceType<typeof ChatInput>>();

// Computed properties
const isWelcomeVisible = computed(() => {
    return (!currentThread.value || !currentThread.value.messages?.length) && messages.value.length === 0;
});

// Methods
function handleSuggestedQuestion(question: string): void {
    chatInput.value?.setMessage(question);
    sendMessage(question);
}

async function scrollToBottom(): Promise<void> {
    await nextTick();
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
}

// Watch for new messages to auto-scroll
watch(
    () => messages.value.length,
    () => {
        scrollToBottom();
    }
);

watch(
    () => isLoading.value,
    () => {
        scrollToBottom();
    }
);

</script>

<style lang="scss" scoped>
.assistant-chat {
    .messages-container {
        scroll-behavior: smooth;
        
        &::-webkit-scrollbar {
            width: 6px;
        }
        
        &::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        &::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
            
            &:hover {
                background: #a1a1a1;
            }
        }
    }

    .typing-indicator {
        animation: fadeIn 0.3s ease-out;
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    }
}

// Dark mode support (if needed)
@media (prefers-color-scheme: dark) {
    .assistant-chat {
        .typing-indicator {
            @apply bg-gray-800;
        }
    }
}
</style>