<template>
	<div class="workflow-canvas-node">
		<div class="node-header flex flex-nowrap items-center space-x-2">
			<span class="node-title flex-grow">{{ node.data.name }}</span>
			<template v-if="isRunning">
				<DotLottieVue
					class="w-8 h-8 bg-sky-900 rounded-full"
					autoplay
					loop
					src="https://lottie.host/e61ac963-4a56-4667-ab2f-b54431a0548d/RSumZz9y00.lottie"
				/>
			</template>
			<template v-else>
				<ActionButton type="edit" color="sky" :disabled="isTemporary" @click.stop="$emit('edit', node)" />
				<ActionButton type="trash" color="red" :disabled="isTemporary" @click.stop="$emit('remove', node)" />
			</template>
		</div>

		<NodeTaskRunWidget class="node-body mt-4" :task-run="taskRun" />

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
import NodeTaskRunWidget from "@/components/Modules/WorkflowCanvas/NodeTaskRunWidget";
import { TaskRun, TaskWorkflowRun } from "@/types";
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import { Edge, Handle, Node, useVueFlow } from "@vue-flow/core";
import { ActionButton } from "quasar-ui-danx";
import { computed } from "vue";

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

const taskRun = computed<TaskRun>(() => props.taskWorkflowRun?.taskRuns?.find((taskRun) => taskRun.task_workflow_node_id == +props.node.id));
const isRunning = computed(() => ["Running"].includes(taskRun.value?.status));
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
