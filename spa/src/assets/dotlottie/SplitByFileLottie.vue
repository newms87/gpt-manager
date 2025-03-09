<template>
	<DotLottiePlayer
		:autoplay="autoplay"
		:finished="finished"
		:final-frame="35"
		src="https://lottie.host/4a298eda-46b8-400e-b9f2-43820e8570d4/I1khJSMkod.lottie"
	/>
</template>
<script setup lang="ts">
import DotLottiePlayer from "@/assets/dotlottie/DotLottiePlayer";

defineProps<{
	autoplay?: boolean;
	finished?: boolean;
}>();
</script>
