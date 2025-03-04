<template>
	<QCard class="bg-slate-800 text-slate-300 rounded overflow-hidden">
		<div class="flex items-center justify-between flex-nowrap">
			<div class="flex-grow py-4 px-3 flex items-center flex-nowrap">
				<div class="text-slate-400">
					ID: {{ workflowRun.id }}
				</div>
				<slot name="name" />
			</div>
			<ShowHideButton
				v-model="showArtifacts"
				:label="workflowRun.artifacts_count + ' Artifacts'"
				class="bg-sky-800 text-sky-200 mx-2"
				@show="dxWorkflowRun.routes.details(props.workflowRun, {'*': false, artifacts: true})"
			/>
			<ShowHideButton
				v-model="showJobs"
				:label="workflowRun.job_runs_count + ' Jobs'"
				class="bg-slate-600 text-slate-200 mx-2"
				@show="dxWorkflowRun.routes.details(props.workflowRun, {'*': false, workflowJobRuns: {'*': true, tasks: {thread: {messages: {files: {transcodes: true}}}}}})"
			/>
			<WorkflowStatusTimerPill :runner="workflowRun" />
			<div class="mx-2">
				<AiTokenUsageButton :usage="workflowRun.usage" />
			</div>
			<div class="mr-1">
				<ActionButton
					type="trash"
					:action="removeWorkflowRunAction"
					:target="workflowRun"
					class="p-4"
					@success="$emit('remove')"
				/>
			</div>
		</div>
		<div class="">
			<div v-if="showArtifacts" class="p-3">
				<ListLoadingContainer
					:loading="!workflowRun.artifacts"
					:empty="workflowRun.artifacts_count === 0"
					empty-text="No artifacts have been created."
				>
					<ArtifactCard
						v-for="artifact in workflowRun.artifacts"
						:key="artifact.id"
						:artifact="artifact"
						class="my-3"
					/>
				</ListLoadingContainer>
			</div>
		</div>
		<div v-if="showJobs" class="p-3">
			<ListLoadingContainer
				:loading="!workflowRun.workflowJobRuns"
				:empty="workflowRun.job_runs_count === 0"
				empty-text="No job runs have been created."
			>
				<WorkflowJobRunCard
					v-for="job in workflowRun.workflowJobRuns"
					:key="job.id"
					:job-run="job"
					class="mt-3"
					:workflow-run="workflowRun"
				/>
			</ListLoadingContainer>
		</div>
	</QCard>
</template>
<script setup lang="ts">
import ArtifactCard from "@/components/Modules/Artifacts/ArtifactCard";
import { dxWorkflow } from "@/components/Modules/Workflows";
import { WORKFLOW_STATUS } from "@/components/Modules/Workflows/config/workflows";
import { WorkflowStatusTimerPill } from "@/components/Modules/Workflows/Shared";
import { dxWorkflowInput } from "@/components/Modules/Workflows/WorkflowInputs/config";
import { dxWorkflowRun } from "@/components/Modules/Workflows/WorkflowRuns/config";
import WorkflowJobRunCard from "@/components/Modules/Workflows/WorkflowRuns/WorkflowJobRunCard";
import ActionButton from "@/components/Shared/Buttons/ActionButton";
import AiTokenUsageButton from "@/components/Shared/Buttons/AiTokenUsageButton";
import ListLoadingContainer from "@/components/Shared/Containers/ListLoadingContainer";
import { WorkflowRun } from "@/types/workflows";
import { autoRefreshObject, ShowHideButton, stopAutoRefreshObject } from "quasar-ui-danx";
import { onMounted, onUnmounted, ref } from "vue";

defineEmits(["remove"]);
const props = defineProps<{
	workflowRun: WorkflowRun;
}>();

const showArtifacts = ref(false);
const showJobs = ref(false);

const removeWorkflowRunAction = dxWorkflowRun.getAction("delete");
removeWorkflowRunAction.onFinish = async () => {
	dxWorkflowInput.activeItem.value && await dxWorkflowInput.getActiveItemDetails();
	dxWorkflow.activeItem.value && await dxWorkflow.getActiveItemDetails();
};

/********
 * Refresh the workflow run every 2 seconds while it is running
 */
onMounted(() => {
	autoRefreshObject(
		props.workflowRun,
		(wr: WorkflowRun) => [WORKFLOW_STATUS.PENDING.value, WORKFLOW_STATUS.RUNNING.value].includes(wr.status),
		(wr: WorkflowRun) => dxWorkflowRun.routes.details(wr)
	);
});

onUnmounted(() => {
	stopAutoRefreshObject(props.workflowRun);
});
</script>
