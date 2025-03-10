<template>
	<DotLottieVue
		ref="lottieRef"
		:autoplay="autoplay"
		:loop="loop"
		:segment="segment"
		:src="src"
		:mode="mode"
	/>
</template>
<script setup lang="ts">
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from "vue";

const emit = defineEmits(["loop", "complete"]);
const props = withDefaults(defineProps<{
	autoplay?: boolean;
	src: string;
	loop?: boolean;
	mode?: "forward" | "reverse" | "bounce" | "reverse-bounce",
	segment?: [number, number];
	startFrame?: number;
	finalFrame?: number;
	finished?: boolean;
}>(), {
	loop: true,
	segment: null,
	startFrame: 0,
	finalFrame: 100,
	mode: "forward"
});

const lottieRef = ref();
const player = computed(() => lottieRef.value.getDotLottieInstance());
onMounted(() => {
	player.value.addEventListener("load", setStoppedFrame);
	player.value.addEventListener("loop", emitLoop);
	player.value.addEventListener("complete", emitComplete);
	setStoppedFrame();
});
onUnmounted(() => {
	player.value.removeEventListener("load", setStoppedFrame);
	player.value.removeEventListener("loop", emitLoop);
	player.value.removeEventListener("complete", emitComplete);
});
watch(() => props.autoplay, () => {
	if (props.autoplay) {
		player.value.play();
	} else {
		player.value.stop();
		setStoppedFrame();
	}
});

watch(() => props.segment, () => {
	player.value.setSegment(props.segment);
});
watch(() => props.finished, setStoppedFrame);
function setStoppedFrame() {
	nextTick(() => player.value.setFrame(props.finished ? props.finalFrame : props.startFrame));
}

function emitLoop() {
	emit("loop", player.value);
}

function emitComplete() {
	emit("complete", player.value);
}
</script>
