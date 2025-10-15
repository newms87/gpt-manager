<template>
    <div
        v-if="errorCount && errorCount > 0"
        class="flex items-center gap-1 px-2 py-1 bg-red-100 rounded-full cursor-pointer hover:bg-red-200 transition-colors"
        :class="[badgeClass, { 'animate-pulse': animate }]"
        @click="showErrorDialog = true"
    >
        <FaSolidTriangleExclamation class="w-3 h-3 text-red-600" />
        <span class="text-xs font-bold text-red-700">{{ errorCount }}</span>
    </div>

    <!-- Error Dialog -->
    <ErrorLogDialog
        v-if="showErrorDialog"
        :url="url"
        @close="showErrorDialog = false"
    />
</template>

<script setup lang="ts">
import { FaSolidTriangleExclamation } from "danx-icon";
import ErrorLogDialog from "./ErrorLogDialog.vue";
import { ref } from "vue";

withDefaults(defineProps<{
    errorCount?: number;
    url: string | null;
    animate?: boolean;
    badgeClass?: string;
}>(), {
    animate: false,
    badgeClass: ""
});

const showErrorDialog = ref(false);
</script>
