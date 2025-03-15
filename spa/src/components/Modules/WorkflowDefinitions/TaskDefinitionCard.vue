<template>
	<div class="py-2 px-4 hover:bg-slate-700">
		<div>{{ taskDefinition.name }}</div>
		<div class="text-xs text-slate-500">{{ taskDefinition.task_agent_count }} Agents</div>
	</div>
</template>
<script setup lang="ts">
import { TaskDefinition } from "@/types";

defineProps<{
	taskDefinition: TaskDefinition;
}>();
</script>
