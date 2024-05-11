<template>
	<div class="p-6">
		Input Workflow Runs
	</div>
</template>
<script setup lang="ts">
import { WorkflowRun } from "@/components/Modules/Workflows/workflows";

defineProps<{
	workflowRuns: WorkflowRun[];
}>();
</script>
