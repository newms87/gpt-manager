<template>
	<DotLottiePlayer
		:autoplay="autoplay"
		src="https://lottie.host/48dd2e58-415d-46e2-9ca3-59c4320ebf1f/NcvOSAIbN7.lottie"
		:finished="finished"
		:start-frame="14"
		:final-frame="14"
	/>
</template>
<script setup lang="ts">
import DotLottiePlayer from "@/assets/dotlottie/DotLottiePlayer";

defineProps<{
	autoplay?: boolean;
	finished?: boolean;
}>();
</script>
