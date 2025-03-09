<template>
	<DotLottieVue
		:key="autoplay ? 'autoplay' : 'no-autoplay'"
		class="bg-sky-900 rounded-full"
		:autoplay="autoplay"
		loop
		:src="assetUrl"
	/>
</template>
<script setup lang="ts">
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import { computed } from "vue";

const props = withDefaults(defineProps<{
	version: "original" | "custom"
	autoplay?: boolean
}>(), {
	version: "original"
});

const assetUrl = computed(() => props.version === "original" ? "https://lottie.host/8136771f-d755-426f-86e5-654531e9f4e2/K4V6IO8kVp.lottie" : "https://lottie.host/1d69e396-4fc7-4642-85f2-64cf2bab3147/X9ACpGSUeY.lottie");
</script>
