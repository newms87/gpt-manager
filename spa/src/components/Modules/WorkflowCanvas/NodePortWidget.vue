<template>
	<Handle
		:id="`${type}-${portId}`"
		:type="type"
		:position="type === 'target' ? Position.Left : Position.Right"
		class="node-handle"
		:class="{'is-connected': isConnected, 'is-target': type=== 'target', 'is-source': type === 'source'}"
		@click="$emit('show-artifacts')"
	>
		<NodeArtifactsButton
			:count="count"
			:active-color="type === 'target' ? 'sky' : 'green'"
			:disabled="disabled"
		/>
	</Handle>
</template>

<script setup lang="ts">
import NodeArtifactsButton from "@/components/Modules/WorkflowCanvas/NodeArtifactsButton";
import { Artifact } from "@/types";
import { Handle, Position } from "@vue-flow/core";

defineEmits<{
	(e: "show-artifacts"): void;
}>();
withDefaults(defineProps<{
	count?: number;
	artifacts?: Artifact[];
	isConnected?: boolean;
	portId: string;
	type: "target" | "source";
	disabled?: boolean;
}>(), {
	count: 0,
	artifacts: null
});
</script>
<style lang="scss" scoped>
.node-handle {
	@apply w-12 h-7 top-[4rem] bg-transparent border-none;

	&:deep(.node-artifacts-button) {
		@apply pointer-events-none;
	}

	&:hover:deep(.node-artifacts-button) {
		@apply outline-4 outline-sky-700 outline;
	}

	&.is-connected:deep(.node-artifacts-button) {
		@apply outline-[3px] outline-green-700 outline;
	}

	&.is-connected:hover:deep(.node-artifacts-button) {
		@apply outline-green-500;
	}
}
</style>
