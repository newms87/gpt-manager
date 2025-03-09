<template>
	<DotLottieVue
		:key="autoplay ? 'autoplay' : 'no-autoplay'"
		:autoplay="autoplay"
		loop
		src="https://lottie.host/b12bc2bd-1e4b-42d2-805a-9469b9955447/DKhDgl4wOZ.lottie"
	/>
</template>
<script setup lang="ts">
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";

defineProps<{
	autoplay?: boolean
}>();
</script>
