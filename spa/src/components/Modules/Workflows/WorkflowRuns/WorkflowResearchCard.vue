<template>
	<div class="px-4 py-2 relative" :class="{'rounded-xl': !showWorkflowRuns, 'rounded-t-xl': showWorkflowRuns}">
		<div class="flex items-center justify-end flex-nowrap">
			<ShowHideButton v-model="showWorkflowRuns" label="Research" />
			<WorkflowStatusTimerPill :runner="bestRunner" class="ml-2" />
		</div>
		<div
			v-if="showWorkflowRuns"
			class="mt-4 space-y-2 absolute top-8 right-0 w-[30rem] p-4 rounded-b-xl rounded-tl-xl bg-slate-700 shadow-lg shadow-slate-800 z-10"
		>
			<div v-for="workflowRun in workflowRuns" :key="workflowRun.id" class="flex items-center flex-nowrap">
				<a :href="workflowRunUrl(workflowRun)" target="_blank" class="font-bold flex-grow">{{
						workflowRun.workflow_name
					}}</a>
				<WorkflowStatusTimerPill :runner="workflowRun" class="ml-4" />
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
import { computed, onMounted, ref } from "vue";

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

const bestRunner = computed(() => props.workflowRuns.find(wr => wr.status === WORKFLOW_STATUS.RUNNING.value) || props.workflowRuns.find(wr => wr.status === WORKFLOW_STATUS.COMPLETED.value) || props.workflowRuns[0]);
function workflowRunUrl(workflowRun: WorkflowRun) {
	return router.resolve({
		name: "workflows",
		params: { id: workflowRun.workflow_id }
	}).href + "/runs";
}
</script>
