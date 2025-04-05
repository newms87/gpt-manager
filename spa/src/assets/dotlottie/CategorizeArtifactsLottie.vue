<template>
	<DotLottiePlayer
		:autoplay="autoplay"
		:finished="finished"
		src="https://lottie.host/2d0cd0d0-9e1e-4569-a2a1-81ae7b4894a1/X0fzlesvMd.lottie"
	/>
</template>
<script setup lang="ts">
import DotLottiePlayer from "@/assets/dotlottie/DotLottiePlayer";

defineProps<{
	autoplay?: boolean;
	finished?: boolean;
}>();
</script>
