<template>
	<DotLottiePlayer
		:autoplay="autoplay"
		:finished="finished"
		src="https://lottie.host/2064fa82-376e-4d4c-a606-d6b6726ce956/DXJM4D06yM.lottie"
	/>
</template>
<script setup lang="ts">
import DotLottiePlayer from "@/assets/dotlottie/DotLottiePlayer";

defineProps<{
	autoplay?: boolean;
	finished?: boolean;
}>();
</script>
