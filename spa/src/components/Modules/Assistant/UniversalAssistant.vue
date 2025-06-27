<template>
    <div class="universal-assistant">
        <!-- Floating Chat Button -->
        <FloatingChatButton
            v-if="!isOpen && !isMinimized"
            :unread-count="unreadCount"
            @click="openChat"
        />

        <!-- Minimized State -->
        <FloatingChatButton
            v-if="isMinimized"
            :unread-count="unreadCount"
            @click="restoreChat"
        />

        <!-- Chat Window -->
        <ChatWindow
            v-if="isOpen && !isMinimized"
            :minimizable="minimizable"
            @close="closeChat"
            @minimize="minimizeChat"
        />
    </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useAssistantDebug } from "@/composables/useAssistantDebug";
import FloatingChatButton from "./FloatingChatButton.vue";
import ChatWindow from "./ChatWindow.vue";

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
const isMinimized = ref(false);
const unreadCount = ref(0);

// Composables
const { debugLog } = useAssistantDebug();

// Methods
function openChat(): void {
    debugLog("UI", "Opening assistant chat interface");
    isOpen.value = true;
    isMinimized.value = false;
    unreadCount.value = 0;
}

function restoreChat(): void {
    debugLog("UI", "Restoring assistant chat from minimized state");
    isMinimized.value = false;
    unreadCount.value = 0;
}

function minimizeChat(): void {
    debugLog("UI", "Minimizing assistant chat interface");
    isMinimized.value = true;
}

function closeChat(): void {
    debugLog("UI", "Closing assistant chat interface");
    isOpen.value = false;
    isMinimized.value = false;
}

// Lifecycle
onMounted(() => {
    if (props.autoOpen) {
        openChat();
    }
});
</script>

<style lang="scss" scoped>
.universal-assistant {
    // All styling is handled by child components
}
</style>