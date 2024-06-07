<template>
	<a class="logo-image block overflow-hidden" target="_blank" :href="url">
		<div v-if="isSvg" class="w-full h-full" v-html="src" />
		<img v-if="isImage" :alt="alt" :src="src" />
	</a>
</template>

<script setup lang="ts">
import { computed } from "vue";

const props = defineProps<{
	src: string;
	alt?: string;
	url?: string;
}>();

const isSvg = computed(() => props.src.startsWith("<svg"));
const isImage = computed(() => !isSvg.value);
</script>

<style scoped lang="scss">
.logo-image {
	&:deep(svg) {
		width: 100%;
		height: 100%;
	}
}
</style>
