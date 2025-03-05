<template>
	<div class="workflow-canvas-node">
		<div class="node-header flex flex-nowrap items-center space-x-2">
			<span class="node-title">{{ node.data.name }} {{ node.id }}</span>
			<ActionButton type="edit" color="sky" :disabled="isTemporary" @click.stop="$emit('edit', node)" />
			<ActionButton type="trash" color="red" :disabled="isTemporary" @click.stop="$emit('remove', node)" />
		</div>

		<div class="node-body">
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
	</div>
</template>

<script setup lang="ts">
import { Edge, Handle, Node, useVueFlow } from "@vue-flow/core";
import { ActionButton } from "quasar-ui-danx";
import { computed } from "vue";

const { edges } = useVueFlow();

defineEmits<{
	(e: "edit", node: Node): void;
	(e: "remove", node: Node): void;
}>();

const props = defineProps<{ node: Node; }>();

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
