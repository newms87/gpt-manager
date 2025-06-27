<template>
    <div
        class="message-item"
        :class="getMessageClasses(message)"
    >
        <div class="message-content">
            <!-- User Message -->
            <div
                v-if="message.role === 'user'"
                class="user-message bg-blue-600 text-white rounded-lg px-4 py-2 max-w-[80%] ml-auto"
            >
                <div class="message-text">{{ message.content }}</div>
                <div class="message-time text-xs opacity-75 mt-1">
                    {{ formatTime(message.created_at) }}
                </div>
            </div>

            <!-- Assistant Message -->
            <div
                v-else-if="message.role === 'assistant'"
                class="assistant-message bg-gray-100 rounded-lg px-4 py-2 max-w-[80%]"
            >
                <div class="flex items-start space-x-2">
                    <FaSolidRobot class="w-4 h-4 text-blue-500 mt-1 flex-shrink-0" />
                    <div class="flex-1">
                        <div
                            class="message-text text-gray-900"
                            v-html="formatMessageContent(message.content)"
                        />
                        <div class="message-time text-xs text-gray-500 mt-1">
                            {{ formatTime(message.created_at) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Error Message -->
            <div
                v-else-if="message.role === 'error'"
                class="error-message bg-red-50 border-l-4 border-red-400 px-4 py-2 max-w-[90%]"
            >
                <div class="flex items-start space-x-2">
                    <FaSolidTriangleExclamation class="w-4 h-4 text-red-500 mt-1 flex-shrink-0" />
                    <div class="flex-1">
                        <div class="text-sm font-medium text-red-800 mb-1">
                            Error occurred
                        </div>
                        <ChatErrorMessage :error="message.error_data" />
                        <div class="text-xs text-red-600 mt-1">
                            {{ formatTime(message.created_at) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tool/System Message -->
            <div
                v-else
                class="system-message bg-yellow-50 border-l-4 border-yellow-400 px-4 py-2 max-w-[80%]"
            >
                <div class="flex items-start space-x-2">
                    <FaSolidGear class="w-4 h-4 text-yellow-500 mt-1 flex-shrink-0" />
                    <div class="flex-1">
                        <div class="text-sm text-yellow-800">
                            {{ message.content }}
                        </div>
                        <div class="text-xs text-yellow-600 mt-1">
                            {{ formatTime(message.created_at) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { fDate } from "quasar-ui-danx";
import { FaSolidTriangleExclamation, FaSolidGear, FaSolidRobot } from "danx-icon";
import { AssistantMessage } from "./types";
import ChatErrorMessage from "./ChatErrorMessage.vue";

interface Props {
    message: AssistantMessage;
}

defineProps<Props>();

function getMessageClasses(message: AssistantMessage): string[] {
    const classes = ['message'];
    
    if (message.role === 'user') {
        classes.push('message-user');
    } else if (message.role === 'assistant') {
        classes.push('message-assistant');
    } else {
        classes.push('message-system');
    }
    
    return classes;
}

function formatTime(timestamp: string): string {
    return fDate(timestamp, 'h:mm A');
}

function formatMessageContent(content: string): string {
    // Check if content is JSON and extract message field
    try {
        const jsonData = JSON.parse(content);
        if (jsonData && typeof jsonData === 'object' && jsonData.message) {
            content = jsonData.message;
        }
    } catch {
        // Not JSON, use content as-is
    }
    
    // Basic markdown-like formatting
    return content
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.*?)\*/g, '<em>$1</em>')
        .replace(/`(.*?)`/g, '<code class="bg-gray-200 px-1 rounded">$1</code>')
        .replace(/\n/g, '<br>');
}
</script>

<style lang="scss" scoped>
.message-item {
    animation: messageSlideIn 0.3s ease-out;
    
    @keyframes messageSlideIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
}

.user-message {
    box-shadow: 0 2px 4px rgba(59, 130, 246, 0.1);
}

.assistant-message {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}
</style>