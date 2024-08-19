<template>
	<div class="px-4 py-2 rounded-xl">
		<div class="flex items-center flex-nowrap">
			<ShowHideButton v-model="showWorkflowRuns" label="Research" />
			<WorkflowStatusTimerPill :runner="workflowRuns[0]" class="ml-2" />
		</div>
		<div class="p-4">
			<div v-for="workflowRun in workflowRuns" :key="workflowRun.id">
				<div class="flex items-center flex-nowrap">
					<a :href="workflowRunUrl(workflowRun)" target="_blank" class="font-bold">{{ workflowRun.name }}</a>
					<WorkflowStatusTimerPill :runner="workflowRuns[0]" class="ml-2" />
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { WORKFLOW_STATUS } from "@/components/Modules/Workflows/consts/workflows";
import { WorkflowStatusTimerPill } from "@/components/Modules/Workflows/Shared";
import { ShowHideButton } from "@/components/Shared";
import router from "@/router";
import { WorkflowRunRoutes } from "@/routes/workflowRoutes";
import { WorkflowRun } from "@/types";
import { autoRefreshObject } from "quasar-ui-danx";
import { onMounted, ref } from "vue";

const props = defineProps<{ workflowRuns: WorkflowRun[], refresh?: boolean }>();

const showWorkflowRuns = ref(false);

onMounted(() => {
	for (const workflowRun of props.workflowRuns) {
		autoRefreshObject(
			workflowRun,
			(wr: WorkflowRun) => props.refresh && wr.status === WORKFLOW_STATUS.RUNNING.value,
			(wr: WorkflowRun) => WorkflowRunRoutes.details(wr)
		);
	}
});

function workflowRunUrl(workflowRun: WorkflowRun) {
	return router.resolve({
		name: "workflows",
		params: { id: workflowRun.workflow_id }
	}).href + "/runs";
}
</script>
