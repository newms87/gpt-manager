<template>
	<QCard class="bg-slate-800 text-slate-300">
		<QCardSection class="flex items-center justify-between">
			<div>
				<a @click="$router.push({name: 'workflows', params: {id: workflowRun.workflow_id}})">
					{{ workflowRun.workflow_name }} ({{ workflowRun.workflow_id }})
				</a>
			</div>
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
