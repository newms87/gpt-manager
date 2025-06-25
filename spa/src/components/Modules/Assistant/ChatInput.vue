<template>
    <div class="message-input border-t border-gray-200 p-4">
        <!-- New Chat Button (shown when there's an active thread) -->
        <div
            v-if="hasActiveThread"
            class="mb-3 flex justify-center"
        >
            <button
                @click="$emit('new-chat')"
                class="flex items-center space-x-2 bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1 rounded-lg transition-colors text-sm"
            >
                <FaSolidPlus class="w-3 h-3" />
                <span>New Chat</span>
            </button>
        </div>
        
        <div class="flex space-x-2">
            <input
                v-model="messageText"
                type="text"
                :placeholder="isThreadRunning ? 'Assistant is processing...' : 'Ask me anything...'"
                class="flex-1 border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                :disabled="disabled || isThreadRunning"
                @keydown.enter="handleSend"
                @keydown.ctrl.enter="handleSend"
            />
            <button
                class="send-button bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white px-4 py-2 rounded-lg transition-colors flex items-center space-x-2"
                :disabled="!messageText.trim() || disabled || isThreadRunning"
                @click="handleSend"
            >
                <FaSolidPaperPlane class="w-4 h-4" />
                <span v-if="!disabled && !isThreadRunning">Send</span>
                <span v-else-if="isThreadRunning">Processing...</span>
                <span v-else>...</span>
            </button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { FaSolidPaperPlane, FaSolidPlus } from "danx-icon";

interface Props {
    hasActiveThread: boolean;
    isThreadRunning: boolean;
    disabled?: boolean;
}

withDefaults(defineProps<Props>(), {
    disabled: false,
});

interface Emits {
    (e: 'send-message', message: string): void;
    (e: 'new-chat'): void;
}

const emit = defineEmits<Emits>();

const messageText = ref('');

function handleSend(): void {
    const message = messageText.value.trim();
    if (!message) return;
    
    emit('send-message', message);
    messageText.value = '';
}

function setMessage(message: string): void {
    messageText.value = message;
}

// Expose method for parent component to set suggested questions
defineExpose({
    setMessage,
});
</script>

<style lang="scss" scoped>
.send-button {
    &:disabled {
        cursor: not-allowed;
    }
    
    &:hover:not(:disabled) {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
    }
}
</style>