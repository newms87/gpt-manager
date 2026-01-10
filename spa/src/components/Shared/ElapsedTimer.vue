<template>
    <LabelPillWidget
        :label="elapsedTime"
        :color="isInProgress ? inProgressColor : completedColor"
        :size="size"
        :class="{ 'animate-pulse': isInProgress }"
    />
</template>
<script setup lang="ts">
import { DateTime, fMillisecondsToDuration, LabelPillWidget, parseDateTime } from "quasar-ui-danx";
import { computed, onUnmounted, ref, watch } from "vue";

const props = withDefaults(defineProps<{
    startTime: string | null | undefined;
    endTime?: string | null | undefined;
    inProgressColor?: string;
    completedColor?: string;
    size?: string;
}>(), {
    endTime: undefined,
    inProgressColor: "amber",
    completedColor: "blue",
    size: "xs"
});

// Reactive timer for live updates
const now = ref(DateTime.now().toISO());
let intervalId: ReturnType<typeof setInterval> | null = null;

// Determine if the timer is still in progress
const isInProgress = computed(() => {
    return !!props.startTime && !props.endTime;
});

// Compute the elapsed time string
const elapsedTime = computed(() => {
    if (!props.startTime) {
        return "-";
    }

    const start = parseDateTime(props.startTime);
    if (!start) {
        return "-";
    }

    const end = props.endTime
        ? parseDateTime(props.endTime)
        : DateTime.fromISO(now.value);

    if (!end) {
        return "-";
    }

    const diffMs = end.toMillis() - start.toMillis();
    return fMillisecondsToDuration(Math.max(0, diffMs));
});

// Setup timer for live updates (every 100ms)
const setupTimer = () => {
    if (!intervalId && isInProgress.value) {
        intervalId = setInterval(() => {
            now.value = DateTime.now().toISO();
        }, 100);
    }
};

const clearTimer = () => {
    if (intervalId) {
        clearInterval(intervalId);
        intervalId = null;
    }
};

// Watch for changes in progress state to manage timer
watch(isInProgress, (inProgress) => {
    if (inProgress) {
        setupTimer();
    } else {
        clearTimer();
    }
}, { immediate: true });

onUnmounted(() => {
    clearTimer();
});
</script>
