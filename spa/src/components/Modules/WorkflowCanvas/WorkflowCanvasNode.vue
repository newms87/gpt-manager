<template>
	<div class="workflow-canvas-node">
		<div class="node-header flex flex-nowrap items-center space-x-2">
			<span class="node-title">{{ node.data.name }}</span>
			<ActionButton type="edit" color="sky" :disabled="isTemporary" @click.stop="$emit('edit', node)" />
			<ActionButton type="trash" color="red" :disabled="isTemporary" @click.stop="$emit('remove', node)" />
		</div>

		<div class="node-body flex items-center flex-nowrap mt-4">
			<LabelPillWidget
				v-if="taskRun?.input_artifacts_count >= 0"
				color="sky"
				size="xs"
				class="flex items-center flex-nowrap flex-shrink-1 cursor-pointer"
				@click="onShowInputArtifacts"
			>
				{{ taskRun.input_artifacts_count }}
				<ArtifactIcon class="w-4 ml-2" />
				<QMenu v-if="isShowingInputArtifacts" :model-value="true" @close="isShowingInputArtifacts = false">
					<ArtifactList :artifacts="taskRun.inputArtifacts" class="p-4" />
				</QMenu>
			</LabelPillWidget>
			<div class="flex-grow"></div>
			<LabelPillWidget
				v-if="taskRun?.output_artifacts_count >= 0"
				color="green"
				size="xs"
				class="flex items-center flex-nowrap flex-shrink-1 cursor-pointer"
				@click="onShowOutputArtifacts"
			>
				{{ taskRun.output_artifacts_count }}
				<ArtifactIcon class="w-4 ml-2" />
				<QMenu v-if="isShowingOutputArtifacts" :model-value="true" @close="isShowingOutputArtifacts = false">
					<ArtifactList :artifacts="taskRun.outputArtifacts" class="p-4" />
				</QMenu>
			</LabelPillWidget>
		</div>

		<!-- Input Ports -->
		<div class="ports input-ports">
			<Handle
				id="target-default"
				type="target"
				position="left"
				class="node-handle"
				:class="{'is-connected': isTargetConnected('target-default')}"
			/>
		</div>

		<!-- Output Ports -->
		<div class="ports output-ports">
			<Handle
				id="source-default"
				type="source"
				position="right"
				class="node-handle"
				:class="{'is-connected': isSourceConnected('source-default')}"
			/>
		</div>
	</div>
</template>

<script setup lang="ts">
import ArtifactList from "@/components/Modules/Artifacts/ArtifactList";
import { dxTaskRun } from "@/components/Modules/TaskDefinitions/TaskRuns/config";
import LabelPillWidget from "@/components/Shared/Widgets/LabelPillWidget";
import { TaskRun, TaskWorkflowRun } from "@/types";
import { Edge, Handle, Node, useVueFlow } from "@vue-flow/core";
import { FaSolidTruckArrowRight as ArtifactIcon } from "danx-icon";
import { ActionButton } from "quasar-ui-danx";
import { computed, ref } from "vue";

const { edges } = useVueFlow();

defineEmits<{
	(e: "edit", node: Node): void;
	(e: "remove", node: Node): void;
}>();

const props = defineProps<{
	node: Node;
	taskWorkflowRun?: TaskWorkflowRun;
}>();

// Is this node a temporary placeholder waiting for the backend to respond with the real node ID
const isTemporary = computed(() => !!props.node.id.match(/^td-/));

const sourceEdges = computed<Edge[]>(() => edges.value.filter((edge) => edge.source === props.node.id.toString()));
const targetEdges = computed<Edge[]>(() => edges.value.filter((edge) => edge.target === props.node.id.toString()));
function isSourceConnected(id) {
	return sourceEdges.value.some((edge) => edge.sourceHandle === id);
}
function isTargetConnected(id) {
	return targetEdges.value.some((edge) => edge.targetHandle === id);
}

const taskRun = computed<TaskRun>(() => props.taskWorkflowRun?.taskRuns?.find((taskRun) => taskRun.task_definition_id === props.node.data.task_definition_id));

const isShowingInputArtifacts = ref(false);
const isShowingOutputArtifacts = ref(false);
async function onShowInputArtifacts() {
	isShowingInputArtifacts.value = true;
	await dxTaskRun.routes.details(taskRun.value);
}
async function onShowOutputArtifacts() {
	isShowingOutputArtifacts.value = true;
	await dxTaskRun.routes.details(taskRun.value);
}
</script>

<style lang="scss">
.workflow-canvas-node {
	@apply border border-gray-300 rounded-xl p-4 bg-sky-800 text-lg;
}

.node-handle {
	@apply w-4 h-4 bg-slate-400;

	&:hover {
		@apply bg-sky-800;
	}

	&.is-connected {
		@apply bg-green-500;
	}
}
</style>
