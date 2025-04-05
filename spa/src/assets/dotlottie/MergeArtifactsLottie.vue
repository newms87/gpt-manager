<template>
	<DotLottiePlayer
		:autoplay="autoplay"
		:finished="finished"
		src="https://lottie.host/571011d6-2e0e-49e5-86dc-d21c14c21305/SJiAxmGRS8.lottie"
		:start-frame="17"
		:final-frame="17"
		:segment="[0,17]"
		:speed=".85"
	/>
</template>
<script setup lang="ts">
import DotLottiePlayer from "@/assets/dotlottie/DotLottiePlayer";

defineProps<{
	autoplay?: boolean;
	finished?: boolean;
}>();
</script>
