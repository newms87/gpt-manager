<template>
	<QCard class="bg-slate-800 text-slate-300 rounded overflow-hidden">
		<div class="flex items-center justify-between">
			<div class="flex-grow cursor-pointer py-4 px-3" @click="showJobsWithStatus = showJobsWithStatus ? '' : 'all'">
				<a @click="$router.push({name: 'workflows', params: {id: workflowRun.workflow_id}})">
					{{ workflowRun.workflow_name }} ({{ workflowRun.id }})
				</a>
			</div>
			<div class="mx-2">
				<ShowHideButton v-model="showArtifacts" :label="artifactCount + ' Artifacts'" class="bg-sky-800 text-sky-200" />
			</div>
			<div class="mx-2">
				<ElapsedTimePill
					timer-class="bg-slate-900 font-bold rounded-lg text-xs w-32 text-center py-2"
					:start="workflowRun.started_at"
					:end="workflowRun.failed_at || workflowRun.completed_at"
				/>
			</div>
			<div class="px-4 py-1.5 rounded-lg mx-2" :class="WORKFLOW_STATUS.resolve(workflowRun.status).classPrimary">
				Workflow {{ workflowRun.status }}
			</div>
			<div class="mr-1">
				<TrashButton :saving="removeWorkflowRunAction.isApplying" class="p-4" @click="onRemove" />
			</div>
		</div>
		<WorkflowJobRunFlow v-model="showJobsWithStatus" :workflow-run="workflowRun" />
		<div class="flex items-stretch">
			<div v-if="showArtifacts" class="p-3">
				<ArtifactCard
					v-for="artifact in workflowRun.artifacts"
					:key="artifact.id"
					:artifact="artifact"
					class="my-3"
				/>
			</div>
		</div>
	</QCard>
</template>
<script setup lang="ts">
import ArtifactCard from "@/components/Modules/Artifacts/ArtifactCard";
import { WORKFLOW_STATUS } from "@/components/Modules/Workflows/consts/workflows";
import { getAction } from "@/components/Modules/Workflows/workflowRunActions";
import ElapsedTimePill from "@/components/Modules/Workflows/WorkflowRuns/ElapsedTimePill";
import WorkflowJobRunFlow from "@/components/Modules/Workflows/WorkflowRuns/WorkflowJobRunFlow";
import ShowHideButton from "@/components/Shared/Buttons/ShowHideButton";
import TrashButton from "@/components/Shared/Buttons/TrashButton";
import { WorkflowRun } from "@/types/workflows";
import { computed, ref } from "vue";

const emit = defineEmits(["remove"]);
const props = defineProps<{
	workflowRun: WorkflowRun;
}>();

const artifactCount = computed(() => props.workflowRun.artifacts?.length);
const showArtifacts = ref(false);
const showJobsWithStatus = ref("");

const removeWorkflowRunAction = getAction("delete");
async function onRemove() {
	await removeWorkflowRunAction.trigger(props.workflowRun);
	emit("remove");
}
</script>
