<template>
	<DotLottieVue
		:key="autoplay ? 'autoplay' : 'no-autoplay'"
		:autoplay="autoplay"
		loop
		src="https://lottie.host/7291ee31-60b8-42af-bf6a-36c6eb2c178c/YNSn9ft2vR.lottie"
	/>
</template>
<script setup lang="ts">
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";

defineProps<{
	autoplay?: boolean
}>();
</script>
