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
                :context="context"
                :context-display-name="contextDisplayName"
                :welcome-message="welcomeMessage"
                :suggested-questions="suggestedQuestions"
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
                v-if="loading || isLoading"
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
        <ChatInput
            ref="chatInput"
            :has-active-thread="!!currentThread"
            :is-thread-running="isThreadRunning"
            :disabled="loading || isLoading"
            @send-message="handleSendMessage"
            @new-chat="handleNewChat"
        />
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

// Props
interface Props {
    thread?: AssistantThread | null;
    context: string;
    contextData?: Record<string, any>;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    thread: null,
    contextData: () => ({}),
    loading: false,
});

// Emits
interface Emits {
    (e: 'message', message: string): void;
    (e: 'thread-created', thread: AssistantThread): void;
}

const emit = defineEmits<Emits>();

// Composable for chat logic
const {
    isLoading,
    currentThread,
    messages,
    isThreadRunning,
    sendMessage,
    startNewChat,
} = useAssistantChat(props.context, props.contextData);

// Template refs
const messagesContainer = ref<HTMLElement>();
const chatInput = ref<InstanceType<typeof ChatInput>>();

// Computed properties
const isWelcomeVisible = computed(() => {
    const activeThread = props.thread || currentThread.value;
    return (!activeThread || !activeThread.messages?.length) && messages.value.length === 0;
});

const contextDisplayName = computed((): string => {
    const names = {
        'schema-editor': 'Schema Editor Assistant',
        'workflow-editor': 'Workflow Assistant', 
        'agent-management': 'Agent Configuration Assistant',
        'task-management': 'Task Management Assistant',
        'general-chat': 'AI Assistant',
    };
    
    return names[props.context as keyof typeof names] || 'AI Assistant';
});

const welcomeMessage = computed((): string => {
    const contextMessages = {
        'schema-editor': 'I can help you design and improve JSON schemas. Tell me about the data structure you want to model!',
        'workflow-editor': 'I can assist with designing and optimizing workflows. What process would you like to automate?',
        'agent-management': 'I can help configure and optimize AI agents. What kind of agent are you setting up?',
        'task-management': 'I can help with task definitions and automation. What task are you trying to create?',
        'general-chat': 'I\'m here to help with any questions about the platform or general assistance.',
    };
    
    return contextMessages[props.context as keyof typeof contextMessages] || contextMessages['general-chat'];
});

const suggestedQuestions = computed((): string[] => {
    const contextQuestions = {
        'schema-editor': [
            'Help me design a schema for user profiles',
            'What validation rules should I add?',
            'How can I improve my current schema?',
        ],
        'workflow-editor': [
            'How do I create an efficient workflow?',
            'What are workflow best practices?',
            'Help me optimize my current workflow',
        ],
        'agent-management': [
            'How do I configure agent parameters?',
            'What makes an effective agent prompt?',
            'Help me troubleshoot agent behavior',
        ],
        'task-management': [
            'How do I create a new task definition?',
            'What are the different task runners?',
            'Help me optimize task performance',
        ],
        'general-chat': [
            'How does this platform work?',
            'What can I do here?',
            'Show me around the features',
        ],
    };
    
    return contextQuestions[props.context as keyof typeof contextQuestions] || contextQuestions['general-chat'];
});

// Methods
async function handleSendMessage(message: string): Promise<void> {
    await sendMessage(message);
    emit('message', message);
    
    // Emit thread-created if this is a new thread
    if (currentThread.value && !props.thread) {
        emit('thread-created', currentThread.value);
    }
}

function handleNewChat(): void {
    startNewChat();
}

function handleSuggestedQuestion(question: string): void {
    chatInput.value?.setMessage(question);
    handleSendMessage(question);
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
    () => props.loading,
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