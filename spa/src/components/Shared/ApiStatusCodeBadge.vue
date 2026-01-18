<template>
    <div class="rounded-2xl px-3 py-1" :class="statusCodeClass">
        {{ displayText }}
    </div>
</template>
<script setup lang="ts">
import { useAuditCardTheme } from "@/composables/useAuditCardTheme";
import { computed } from "vue";

const props = withDefaults(defineProps<{
    statusCode: number | null | undefined;
    showPending?: boolean;
}>(), {
    showPending: true
});

const { isDark } = useAuditCardTheme();

const statusCodeClass = computed(() => {
    // null/undefined = in-progress (gray)
    if (props.statusCode === null || props.statusCode === undefined) {
        return isDark.value ? "bg-slate-600 text-slate-200" : "bg-slate-200 text-slate-700";
    }
    // 0 = timeout/connection error (red)
    if (props.statusCode === 0) {
        return isDark.value ? "bg-red-800 text-red-100" : "bg-red-100 text-red-800 border border-red-300";
    }
    // >= 400 = HTTP error (red)
    if (props.statusCode >= 400) {
        return isDark.value ? "bg-red-800 text-red-100" : "bg-red-100 text-red-800 border border-red-300";
    }
    // >= 300 = redirect (yellow)
    if (props.statusCode >= 300) {
        return isDark.value ? "bg-yellow-700 text-yellow-100" : "bg-yellow-100 text-yellow-800 border border-yellow-300";
    }
    // >= 200 = success (green)
    if (props.statusCode >= 200) {
        return isDark.value ? "bg-green-800 text-green-100" : "bg-green-100 text-green-800 border border-green-300";
    }
    // < 200 = informational (blue)
    return isDark.value ? "bg-blue-700 text-blue-100" : "bg-blue-100 text-blue-800 border border-blue-300";
});

const displayText = computed(() => {
    // null/undefined = in-progress
    if (props.statusCode === null || props.statusCode === undefined) {
        return props.showPending ? "..." : "";
    }
    // 0 = timeout/connection error
    if (props.statusCode === 0) {
        return "ERR";
    }
    return props.statusCode;
});
</script>
