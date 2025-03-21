<template>
	<div class="bg-sky-950 p-4 rounded">
		Source / Target
	</div>
</template>
<script setup lang="ts">
import { TaskDefinition } from "@/types";

defineProps<{
	targetTaskDefinition: TaskDefinition;
	sourceTaskDefinitions: TaskDefinition[];
}>();
</script>
