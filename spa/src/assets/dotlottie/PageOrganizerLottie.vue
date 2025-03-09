<template>
	<DotLottiePlayer
		:autoplay="autoplay"
		:finished="finished"
		src="https://lottie.host/8136771f-d755-426f-86e5-654531e9f4e2/K4V6IO8kVp.lottie"
	/>
</template>
<script setup lang="ts">
import DotLottiePlayer from "@/assets/dotlottie/DotLottiePlayer";

defineProps<{
	autoplay?: boolean;
	finished?: boolean;
}>();
</script>
