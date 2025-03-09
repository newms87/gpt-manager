<template>
	<DotLottiePlayer
		:autoplay="autoplay"
		:finished="finished"
		:start-frame="18"
		:final-frame="35"
		:segment="[4,50]"
		src="https://lottie.host/d72b3078-48d2-4a3f-ac8b-d00946923722/H1Osm0gpAx.lottie"
	/>
</template>
<script setup lang="ts">
import DotLottiePlayer from "@/assets/dotlottie/DotLottiePlayer";

defineProps<{
	autoplay?: boolean;
	finished?: boolean;
}>();
</script>
