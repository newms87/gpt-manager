<template>
	<a class="w-full max-w-full overflow-hidden" target="_blank" :href="url">
		<div v-if="isSvg" class="max-w-full" v-html="src" />
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
