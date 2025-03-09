<template>
	<DotLottieVue
		:key="autoplay ? 'autoplay' : 'no-autoplay'"
		class="bg-sky-900 rounded-full"
		:autoplay="autoplay"
		loop
		src="https://lottie.host/30368a65-c02b-41d4-8206-351e15c58765/KJRQpxxoBv.lottie"
	/>
</template>
<script setup lang="ts">
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";

defineProps<{
	autoplay?: boolean
}>();
</script>
