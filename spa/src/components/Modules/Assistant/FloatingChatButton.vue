<template>
    <div 
        class="floating-chat-button fixed bottom-4 right-4 z-50 cursor-pointer"
        @click="handleClick"
    >
        <div
            class="chat-trigger bg-blue-600 hover:bg-blue-700 rounded-full p-4 shadow-lg transition-all duration-300 hover:scale-110"
        >
            <FaSolidComments class="w-6 h-6 text-white" />
            <div
                v-if="hasUnreadMessages"
                class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 rounded-full flex items-center justify-center"
            >
                <span class="text-xs text-white font-bold">{{ unreadCount }}</span>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { FaSolidComments } from "danx-icon";

// Props
interface Props {
    unreadCount?: number;
}

const props = withDefaults(defineProps<Props>(), {
    unreadCount: 0,
});

// Emits
interface Emits {
    (e: 'click'): void;
}

const emit = defineEmits<Emits>();

// Computed
const hasUnreadMessages = computed(() => props.unreadCount > 0);

// Methods
function handleClick(): void {
    emit('click');
}
</script>

<style lang="scss" scoped>
.floating-chat-button {
    .chat-trigger {
        position: relative;

        &:hover {
            box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.5);
        }
    }
}
</style>