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

// Methods
function openChat(): void {
    isOpen.value = true;
    isMinimized.value = false;
    unreadCount.value = 0;
}

function restoreChat(): void {
    isMinimized.value = false;
    unreadCount.value = 0;
}

function minimizeChat(): void {
    isMinimized.value = true;
}

function closeChat(): void {
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