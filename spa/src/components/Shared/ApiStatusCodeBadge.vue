<template>
    <div class="rounded-2xl px-3 py-1" :class="statusCodeClass">
        {{ displayText }}
    </div>
</template>
<script setup lang="ts">
import { computed } from "vue";

const props = withDefaults(defineProps<{
    statusCode: number | null | undefined;
    showPending?: boolean;
}>(), {
    showPending: true
});

const statusCodeClass = computed(() => {
    if (!props.statusCode || props.statusCode === 0) {
        return "bg-slate-600";
    }
    if (props.statusCode >= 400) return "bg-red-800";
    if (props.statusCode >= 300) return "bg-yellow-700";
    return "bg-green-800";
});

const displayText = computed(() => {
    if (!props.statusCode || props.statusCode === 0) {
        return props.showPending ? "..." : "";
    }
    return props.statusCode;
});
</script>
