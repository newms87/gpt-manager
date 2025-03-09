<template>
	<DotLottiePlayer
		:autoplay="autoplay"
		:finished="finished"
		src="https://lottie.host/30368a65-c02b-41d4-8206-351e15c58765/KJRQpxxoBv.lottie"
	/>
</template>
<script setup lang="ts">
import DotLottiePlayer from "@/assets/dotlottie/DotLottiePlayer";

defineProps<{
	autoplay?: boolean;
	finished?: boolean;
}>();
</script>
