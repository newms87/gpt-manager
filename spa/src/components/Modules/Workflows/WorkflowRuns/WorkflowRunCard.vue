<template>
	<QCard class="bg-slate-800 text-slate-300 rounded overflow-hidden">
		<div class="flex items-center justify-between">
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
				@show="WorkflowRunRoutes.relation(props.workflowRun, 'artifacts')"
			/>
			<ShowHideButton
				v-model="showJobs"
				:label="workflowRun.job_runs_count + ' Jobs'"
				class="bg-slate-600 text-slate-200 mx-2"
				@show="WorkflowRunRoutes.relation(props.workflowRun, 'workflowJobRuns')"
			/>
			<div class="mx-2">
				<ElapsedTimePill
					timer-class="bg-slate-900 font-bold rounded-lg text-xs w-32 text-center py-2"
					:start="workflowRun.started_at"
					:end="workflowRun.failed_at || workflowRun.completed_at"
				/>
			</div>
			<div
				class="px-4 py-1.5 rounded-lg mx-2 w-28 text-center"
				:class="WORKFLOW_STATUS.resolve(workflowRun.status).classPrimary"
			>
				{{ workflowRun.status }}
			</div>
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
import { WORKFLOW_STATUS } from "@/components/Modules/Workflows/consts/workflows";
import { WorkflowController } from "@/components/Modules/Workflows/workflowControls";
import { WorkflowInputController } from "@/components/Modules/Workflows/WorkflowInputs/workflowInputControls";
import { getAction } from "@/components/Modules/Workflows/workflowRunActions";
import ElapsedTimePill from "@/components/Modules/Workflows/WorkflowRuns/ElapsedTimePill";
import WorkflowJobRunCard from "@/components/Modules/Workflows/WorkflowRuns/WorkflowJobRunCard";
import { ShowHideButton } from "@/components/Shared";
import ActionButton from "@/components/Shared/Buttons/ActionButton";
import AiTokenUsageButton from "@/components/Shared/Buttons/AiTokenUsageButton";
import ListLoadingContainer from "@/components/Shared/Containers/ListLoadingContainer";
import { WorkflowRunRoutes } from "@/routes/workflowRoutes";
import { WorkflowRun } from "@/types/workflows";
import { storeObject } from "quasar-ui-danx";
import { onMounted, ref, watch } from "vue";

defineEmits(["remove"]);
const props = defineProps<{
	workflowRun: WorkflowRun;
}>();

const showArtifacts = ref(false);
const showJobs = ref(false);

const removeWorkflowRunAction = getAction("delete");
removeWorkflowRunAction.onFinish = async () => {
	WorkflowInputController.activeItem.value && await WorkflowInputController.getActiveItemDetails();
	WorkflowController.activeItem.value && await WorkflowController.getActiveItemDetails();
};

/********
 * Refresh the workflow run every 2 seconds while it is running
 */
onMounted(refreshWorkflowInput);
watch(() => props.workflowRun, refreshWorkflowInput);

let refreshTimeout = null;
function refreshWorkflowInput() {
	if (refreshTimeout) {
		clearTimeout(refreshTimeout);
		refreshTimeout = null;
	}

	refreshTimeout = setTimeout(async () => {
		if ([WORKFLOW_STATUS.PENDING.value, WORKFLOW_STATUS.RUNNING.value].includes(props.workflowRun.status)) {
			storeObject(await WorkflowRunRoutes.details(props.workflowRun));
			refreshWorkflowInput();
		}
	}, 2000);
}
</script>
