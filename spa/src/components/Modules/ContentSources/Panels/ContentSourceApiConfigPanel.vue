<template>
	<div class="p-6 flex flex-col items-stretch flex-nowrap">

	</div>
</template>
<script setup lang="ts">
import { ContentSource } from "@/types";
import { ref } from "vue";

const props = defineProps<{
	contentSource: ContentSource,
}>();

const input = ref({
	config: {
		method: "GET"

	},
	name: props.contentSource.name,
	url: props.contentSource.url
});
</script>
