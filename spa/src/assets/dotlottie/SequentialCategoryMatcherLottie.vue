<template>
	<DotLottiePlayer
		:autoplay="autoplay"
		src="https://lottie.host/62313919-6fcb-4e5a-8119-86b567ae563c/TW4vkvMiXW.lottie"
		:finished="finished"
	/>
</template>
<script setup lang="ts">
import DotLottiePlayer from "@/assets/dotlottie/DotLottiePlayer";

defineProps<{
	autoplay?: boolean;
	finished?: boolean;
}>();
</script>
