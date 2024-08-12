<template>
	<div class="px-4 py-2 rounded-xl">
		<div class="flex items-center flex-nowrap">
			<a :href="workflowRunsUrl" target="_blank">
				Research
			</a>
			<WorkflowStatusTimerPill :runner="workflowRun" class="ml-2" />
		</div>
	</div>
</template>

<script setup lang="ts">
import { WORKFLOW_STATUS } from "@/components/Modules/Workflows/consts/workflows";
import { WorkflowStatusTimerPill } from "@/components/Modules/Workflows/Shared";
import router from "@/router";
import { WorkflowRunRoutes } from "@/routes/workflowRoutes";
import { WorkflowRun } from "@/types";
import { autoRefreshObject } from "quasar-ui-danx";
import { computed, onMounted } from "vue";

const props = defineProps<{ workflowRun: WorkflowRun, refresh?: boolean }>();

onMounted(() => {
	autoRefreshObject(
		props.workflowRun,
		(wr: WorkflowRun) => props.refresh && wr.status === WORKFLOW_STATUS.RUNNING.value,
		(wr: WorkflowRun) => WorkflowRunRoutes.details(wr)
	);
});

const workflowRunsUrl = computed(() => router.resolve({
	name: "workflows",
	params: { id: props.workflowRun.workflow_id }
}).href + "/runs");
</script>
