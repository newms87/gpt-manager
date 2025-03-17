<template>
	<DotLottiePlayer
		:autoplay="autoplay"
		:finished="finished"
		src="https://lottie.host/753a35cd-ca97-4aa7-af21-b8341d6b76a3/c2RtM7r4qw.lottie"
		:start-frame="21"
		:final-frame="60"
	/>
</template>
<script setup lang="ts">
import DotLottiePlayer from "@/assets/dotlottie/DotLottiePlayer";

defineProps<{
	autoplay?: boolean;
	finished?: boolean;
}>();
</script>
