<template>
	<div :class="timerClass">{{ taskTimer }}</div>
</template>
<script lang="ts" setup>
import { DateTime, fElapsedTime } from "quasar-ui-danx";
import { onMounted, onUnmounted, ref } from "vue";

export interface ElapsedTimePillProps {
	start?: string;
	end?: string;
	timerClass?: string;
}

const props = withDefaults(defineProps<ElapsedTimePillProps>(), {
	start: null,
	end: null,
	timerClass: "py-1 px-3 bg-slate-800 rounded-xl text-xs w-28 text-center"
});

const taskTimer = ref(calcTaskTimer());
onMounted(() => {
	const interval = setInterval(() => {
		taskTimer.value = calcTaskTimer();
	}, 1000);
	onUnmounted(() => clearInterval(interval));
});

function calcTaskTimer() {
	return fElapsedTime(props.start, props.end || DateTime.now());
}

</script>
