<template>
	<QCard class="bg-slate-800 text-slate-300 rounded overflow-hidden">
		<div class="flex items-center justify-between p-3">
			<div>
				<a @click="$router.push({name: 'workflows', params: {id: workflowRun.workflow_id}})">
					{{ workflowRun.workflow_name }} ({{ workflowRun.id }})
				</a>
			</div>
			<div>{{ workflowRun.status }}</div>
			<div>{{ fDateTime(workflowRun.started_at) }}</div>
			<div>
				<TrashButton :saving="removeWorkflowRunAction.isApplying" @click="onRemove" />
			</div>
		</div>
		<WorkflowJobRunFlow :workflow-run="workflowRun" />
		<div class="flex items-stretch">
			<div class="p-3">
				<div v-if="artifactCount === 0" class="text-slate-300">No results</div>
				<template v-else>
					<div class="text-sky-800 p-2">{{ artifactCount }} Results</div>
					<ArtifactCard v-for="artifact in workflowRun.artifacts" :key="artifact.id" :artifact="artifact" />
				</template>
			</div>
		</div>
	</QCard>
</template>
<script setup lang="ts">
import ArtifactCard from "@/components/Modules/Artifacts/ArtifactCard";
import { getAction } from "@/components/Modules/Workflows/workflowRunActions";
import WorkflowJobRunFlow from "@/components/Modules/Workflows/WorkflowRuns/WorkflowJobRunFlow";
import TrashButton from "@/components/Shared/Buttons/TrashButton";
import { WorkflowRun } from "@/types/workflows";
import { fDateTime } from "quasar-ui-danx";
import { computed } from "vue";

const emit = defineEmits(["remove"]);
const props = defineProps<{
	workflowRun: WorkflowRun;
}>();

const artifactCount = computed(() => props.workflowRun.artifacts?.length);

const removeWorkflowRunAction = getAction("delete");
async function onRemove() {
	await removeWorkflowRunAction.trigger(props.workflowRun);
	emit("remove");
}
</script>
