<template>
	<div class="p-6 flex flex-col items-stretch flex-nowrap">
		API CONFIG
	</div>
</template>
<script setup lang="ts">
import { ContentSource } from "@/types";

defineProps<{
	contentSource: ContentSource,
}>();
</script>
