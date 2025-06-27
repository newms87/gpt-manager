<template>
	<div class="node-ports-widget flex-x space-x-2 h-[5rem]">
		<div v-if="!hideTarget" class="ports input-ports">
			<NodePortWidget
				v-for="targetPort in targetPorts"
				:key="`port-${targetPort}`"
				:port-id="targetPort"
				type="target"
				:is-connected="isTargetConnected('target-' + targetPort)"
				:count="taskRun?.input_artifacts_count"
				:disabled="!taskRun"
				@show-artifacts="onShowInputArtifacts"
			/>
		</div>

		<div v-if="!hideSource" class="ports output-ports">
			<NodePortWidget
				v-for="sourcePort in sourcePorts"
				:key="`port-${sourcePort}`"
				:port-id="sourcePort"
				type="source"
				:is-connected="isSourceConnected('source-' + sourcePort)"
				:count="taskRun?.output_artifacts_count"
				:disabled="!taskRun"
				@show-artifacts="onShowOutputArtifacts"
			/>
		</div>

		<InfoDialog
			v-if="isShowingInputArtifacts || isShowingOutputArtifacts"
			:title="`${taskRun.taskDefinition.name}: ${isShowingInputArtifacts ? 'Input' : 'Output'} Artifacts`"
			hide-done
			@close="hideArtifacts"
		>
			<ArtifactList
				:filter="artifactsFilter"
				class="w-[80vw] h-[80vh]"
			/>
		</InfoDialog>
	</div>
</template>

<script setup lang="ts">
import ArtifactList from "@/components/Modules/Artifacts/ArtifactList";
import NodePortWidget from "@/components/Modules/WorkflowCanvas/NodePortWidget";
import { TaskRun } from "@/types";
import { Edge } from "@vue-flow/core";
import { AnyObject, InfoDialog } from "quasar-ui-danx";
import { computed, ref } from "vue";

const props = withDefaults(defineProps<{
	taskRun?: TaskRun;
	sourceEdges: Edge[];
	targetEdges: Edge[];
	hideSource?: boolean;
	hideTarget?: boolean;
	sourcePorts?: string[];
	targetPorts?: string[];
}>(), {
	taskRun: null,
	sourcePorts: () => ["default"],
	targetPorts: () => ["default"]
});

function isSourceConnected(id) {
	return props.sourceEdges.some((edge) => edge.sourceHandle === id);
}
function isTargetConnected(id) {
	return props.targetEdges.some((edge) => edge.targetHandle === id);
}

const isShowingInputArtifacts = ref(false);
const isShowingOutputArtifacts = ref(false);

const artifactsFilter: AnyObject = computed(() => ({
	taskRun: {
		artifactable_id: props.taskRun.id,
		category: isShowingInputArtifacts.value ? "input" : "output"
	}
}));

async function onShowInputArtifacts() {
	if (!props.taskRun) return;
	isShowingInputArtifacts.value = true;
	isShowingOutputArtifacts.value = false;
}
async function onShowOutputArtifacts() {
	if (!props.taskRun) return;
	isShowingInputArtifacts.value = false;
	isShowingOutputArtifacts.value = true;
}
function hideArtifacts() {
	isShowingInputArtifacts.value = false;
	isShowingOutputArtifacts.value = false;
}
</script>
