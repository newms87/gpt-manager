<template>
	<DotLottieVue
		ref="lottieRef"
		:autoplay="autoplay"
		loop
		:segment="segment"
		:src="src"
	/>
</template>
<script setup lang="ts">
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import { computed, nextTick, onMounted, ref, watch } from "vue";

const props = withDefaults(defineProps<{
	autoplay?: boolean;
	src: string;
	segment?: [number, number];
	startFrame?: number;
	finalFrame?: number;
	finished?: boolean;
}>(), {
	segment: null,
	startFrame: 0,
	finalFrame: 100
});

const lottieRef = ref();
const player = computed(() => lottieRef.value.getDotLottieInstance());
onMounted(() => {
	player.value.addEventListener("load", setStoppedFrame);
	setStoppedFrame();
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
</script>
