<template>
	<DotLottieVue
		:key="autoplay ? 'autoplay' : 'no-autoplay'"
		:autoplay="autoplay"
		loop
		src="https://gpt-static-assets.s3.us-east-1.amazonaws.com/lottie/save-to-db.lottie"
	/>
</template>
<script setup lang="ts">
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";

defineProps<{
	autoplay?: boolean
}>();
</script>
