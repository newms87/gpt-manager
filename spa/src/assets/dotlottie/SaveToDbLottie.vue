<template>
	<DotLottieVue
		:key="autoplay ? 'autoplay' : 'no-autoplay'"
		:autoplay="autoplay"
		loop
		:segment="autoplay ? [4,50] : [50,50]"
		src="https://lottie.host/d72b3078-48d2-4a3f-ac8b-d00946923722/H1Osm0gpAx.lottie"
	/>
</template>
<script setup lang="ts">
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";

defineProps<{
	autoplay?: boolean
}>();
</script>
