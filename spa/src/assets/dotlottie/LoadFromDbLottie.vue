<template>
	<DotLottiePlayer
		:autoplay="autoplay"
		src="https://lottie.host/69ba00e7-9ee5-4b1e-86ba-789f5e3289f6/0obrgcGaoj.lottie"
		:finished="finished"
	/>
</template>
<script setup lang="ts">
import DotLottiePlayer from "@/assets/dotlottie/DotLottiePlayer";

defineProps<{
	autoplay?: boolean;
	finished?: boolean;
}>();
</script>
