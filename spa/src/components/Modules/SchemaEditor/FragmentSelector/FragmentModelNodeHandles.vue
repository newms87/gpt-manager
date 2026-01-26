<template>
	<!-- Target Handles -->
	<template v-if="type === 'target'">
		<!-- Left handle for LR layout -->
		<Handle
			id="target-left"
			type="target"
			:position="Position.Left"
			:class="[
				'!bg-transparent !border-0 z-20',
				{ '!opacity-0': isRoot || direction !== 'LR' }
			]"
		>
			<ArrayIndicatorDots v-if="isArray" :direction="direction" />
			<ObjectIndicatorDot v-else :direction="direction" />
		</Handle>
		<!-- Top handle for TB layout -->
		<Handle
			id="target-top"
			type="target"
			:position="Position.Top"
			:class="[
				'!bg-transparent !border-0 z-20',
				{ '!opacity-0': isRoot || direction !== 'TB' }
			]"
		>
			<ArrayIndicatorDots v-if="isArray" :direction="direction" />
			<ObjectIndicatorDot v-else :direction="direction" />
		</Handle>
	</template>

	<!-- Source Handles -->
	<template v-else>
		<Handle
			v-for="handle in sourceHandles"
			:id="handle.id"
			:key="handle.id"
			type="source"
			:position="handle.position"
			class="!bg-transparent !border-0"
			:class="{ '!opacity-0': direction !== handle.direction || (!editEnabled && !hasModelChildren) }"
		>
			<ObjectIndicatorDot
				v-if="direction === handle.direction && hasModelChildren && !editEnabled"
				:direction="direction"
			/>
		</Handle>
	</template>
</template>

<script setup lang="ts">
import { Handle, Position } from "@vue-flow/core";
import ArrayIndicatorDots from "./ArrayIndicatorDots.vue";
import ObjectIndicatorDot from "./ObjectIndicatorDot.vue";
import { LayoutDirection } from "./types";

// Source handle configurations for both layout directions
const sourceHandles: Array<{ id: string; position: typeof Position.Right | typeof Position.Bottom; direction: LayoutDirection }> = [
	{ id: "source-right", position: Position.Right, direction: "LR" },
	{ id: "source-bottom", position: Position.Bottom, direction: "TB" }
];

defineProps<{
	type: "target" | "source";
	direction: LayoutDirection;
	isArray: boolean;
	hasModelChildren: boolean;
	editEnabled: boolean;
	isRoot: boolean;
}>();
</script>
