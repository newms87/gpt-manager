<template>
	<div class="flex items-center flex-nowrap space-x-2">
		<div class="ports input-ports">
			<NodePortWidget
				port-id="default"
				type="target"
				:is-connected="isTargetConnected('target-default')"
				:count="taskRun?.input_artifacts_count"
				:disabled="!taskRun"
				:artifacts="taskRun?.inputArtifacts"
				@show-artifacts="onShowInputArtifacts"
			/>
		</div>

		<div class="ports output-ports">
			<NodePortWidget
				port-id="default"
				type="source"
				:is-connected="isSourceConnected('source-default')"
				:count="taskRun?.output_artifacts_count"
				:disabled="!taskRun"
				:artifacts="taskRun?.outputArtifacts"
				@show-artifacts="onShowOutputArtifacts"
			/>
		</div>

		<InfoDialog
			v-if="artifactsToShow"
			:title="`${taskRun.taskDefinition.name}: ${isShowingInputArtifacts ? 'Input' : 'Output'} Artifacts`"
			@close="hideArtifacts"
		>
			<ArtifactList :artifacts="artifactsToShow" class="w-[60rem] h-[80vh]" />
		</InfoDialog>
	</div>
</template>

<script setup lang="ts">
import ArtifactList from "@/components/Modules/Artifacts/ArtifactList";
import { dxTaskRun } from "@/components/Modules/TaskDefinitions/TaskRuns/config";
import NodePortWidget from "@/components/Modules/WorkflowCanvas/NodePortWidget";
import { Artifact, TaskRun } from "@/types";
import { Edge } from "@vue-flow/core";
import { InfoDialog } from "quasar-ui-danx";
import { computed, ref } from "vue";

const props = defineProps<{
	taskRun?: TaskRun;
	sourceEdges: Edge[];
	targetEdges: Edge[];
}>();

function isSourceConnected(id) {
	return props.sourceEdges.some((edge) => edge.sourceHandle === id);
}
function isTargetConnected(id) {
	return props.targetEdges.some((edge) => edge.targetHandle === id);
}

const isShowingInputArtifacts = ref(false);
const isShowingOutputArtifacts = ref(false);
const artifactsToShow = computed<Artifact[] | null>(() => isShowingInputArtifacts.value ? props.taskRun.inputArtifacts : (isShowingOutputArtifacts.value ? props.taskRun.outputArtifacts : null));
const artifactsField = {
	text_content: true,
	json_content: true,
	files: { transcodes: true, thumb: true }
};
async function onShowInputArtifacts() {
	if (!props.taskRun) return;
	isShowingInputArtifacts.value = true;
	isShowingOutputArtifacts.value = false;
	await dxTaskRun.routes.details(props.taskRun, { inputArtifacts: artifactsField });
}
async function onShowOutputArtifacts() {
	if (!props.taskRun) return;
	isShowingInputArtifacts.value = false;
	isShowingOutputArtifacts.value = true;
	await dxTaskRun.routes.details(props.taskRun, { outputArtifacts: artifactsField });
}
function hideArtifacts() {
	isShowingInputArtifacts.value = false;
	isShowingOutputArtifacts.value = false;
}
</script>
