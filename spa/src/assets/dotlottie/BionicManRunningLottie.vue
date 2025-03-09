<template>
	<DotLottiePlayer
		:autoplay="autoplay"
		:finished="finished"
		src="https://lottie.host/e61ac963-4a56-4667-ab2f-b54431a0548d/RSumZz9y00.lottie"
	/>
</template>
<script setup lang="ts">
import DotLottiePlayer from "@/assets/dotlottie/DotLottiePlayer";

defineProps<{
	autoplay?: boolean;
	finished?: boolean;
}>();
</script>
