<template>
	<QCard>
		<QCardSection class="flex items-center justify-between">
			<div>Workflow Run {{ workflowRun.id }}</div>
			<div>{{ workflowRun.status }}</div>
			<div>{{ fDateTime(workflowRun.started_at) }}</div>
		</QCardSection>
	</QCard>
</template>
<script setup lang="ts">
import { WorkflowRun } from "@/components/Modules/Workflows/workflows";
import { fDateTime } from "quasar-ui-danx";

defineProps<{
	workflowRun: WorkflowRun;
}>();
</script>
