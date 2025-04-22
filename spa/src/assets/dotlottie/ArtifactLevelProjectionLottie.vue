<template>
	<DotLottiePlayer
		:autoplay="autoplay"
		:finished="finished"
		src="https://lottie.host/b827505e-9b01-4e58-b790-7fd5a7c1e7e2/44sGw7BWCa.lottie"
	/>
</template>
<script setup lang="ts">
import DotLottiePlayer from "./DotLottiePlayer.vue";

defineProps<{
	autoplay?: boolean;
	finished?: boolean;
}>();
</script>
