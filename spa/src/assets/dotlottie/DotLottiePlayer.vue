<template>
	<DotLottieVue
		ref="lottieRef"
		:autoplay="autoplay"
		:loop="loop"
		:segment="segment"
		:src="src"
		:speed="speed"
		:mode="mode"
		:class="$props.class"
		@mouseover="onHoverIn"
		@mouseout="onHoverOut"
	/>
</template>
<script setup lang="ts">
import { onViewportChangeEnd } from "@/components/Modules/WorkflowCanvas/helpers";
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from "vue";

const emit = defineEmits(["loop", "complete"]);
const props = withDefaults(defineProps<{
	autoplay?: boolean;
	src: string;
	loop?: boolean;
	speed?: number;
	mode?: "forward" | "reverse" | "bounce" | "reverse-bounce",
	segment?: [number, number];
	startFrame?: number;
	finalFrame?: number;
	finished?: boolean;
	playOnHover?: boolean;
	class?: string;
}>(), {
	loop: true,
	segment: null,
	startFrame: 0,
	finalFrame: 100,
	speed: 1,
	mode: "forward",
	class: "w-full h-full"
});

const lottieRef = ref();
const player = computed(() => lottieRef.value.getDotLottieInstance());

// Always keep the player size in sync with the canvas / window size so the animation appears full size and sharp
onViewportChangeEnd(async () => {
	if (player.value) {
		await nextTick();
		player.value.resize();
	}
});

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

function onHoverIn() {
	if (props.playOnHover) {
		player.value.play();
	}
}

function onHoverOut() {
	if (props.playOnHover) {
		player.value.stop();
		setStoppedFrame();
	}
}
</script>
