<template>
	<div
		v-if="editEnabled"
		class="absolute z-20 pointer-events-auto nodrag nopan"
		:class="buttonPosition"
	>
		<div
			class="w-6 h-6 bg-green-600 hover:bg-green-500 rounded-full flex items-center justify-center cursor-pointer transition-colors"
			@click.stop="emit('add-child-model')"
		>
			<PlusIcon class="w-3 h-3 text-white" />
		</div>
	</div>
</template>

<script setup lang="ts">
import { FaSolidPlus as PlusIcon } from "danx-icon";
import { computed } from "vue";
import { LayoutDirection } from "./types";

const props = defineProps<{
	editEnabled: boolean;
	direction: LayoutDirection;
}>();

const emit = defineEmits<{
	"add-child-model": [];
}>();

// Compute position classes for the Add Model button based on layout direction
const buttonPosition = computed(() => {
	if (props.direction === "LR") {
		// Right edge, vertically centered
		return "right-0 top-1/2 -translate-y-1/2 translate-x-1/2";
	} else {
		// Bottom edge, horizontally centered
		return "bottom-0 left-1/2 -translate-x-1/2 translate-y-1/2";
	}
});
</script>
