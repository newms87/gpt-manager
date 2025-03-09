<template>
	<DotLottieVue
		:key="autoplay ? 'autoplay' : 'no-autoplay'"
		:autoplay="autoplay"
		loop
		src="https://lottie.host/69ba00e7-9ee5-4b1e-86ba-789f5e3289f6/0obrgcGaoj.lottie"
	/>
</template>
<script setup lang="ts">
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";

defineProps<{
	autoplay?: boolean
}>();
</script>
