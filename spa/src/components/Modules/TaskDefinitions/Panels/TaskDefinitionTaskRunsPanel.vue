<template>
	<div class="p-6">
		Task Runs
	</div>
</template>
<script setup lang="ts">

import { TaskDefinition } from "@/types/task-definitions";

defineProps<{
	agent: TaskDefinition,
}>();
</script>
