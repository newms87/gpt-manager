<template>
	<div class="p-6">
		<WorkflowRunCard
			v-for="workflowRun in workflow.runs"
			:key="workflowRun.id"
			:workflow-run="workflowRun"
			class="mb-2"
		/>
	</div>
</template>
<script setup lang="ts">
import { WorkflowRunCard } from "@/components/Modules/Workflows/WorkflowRuns";
import { Workflow } from "@/types/workflows";

defineProps<{
	workflow: Workflow,
}>();
</script>
