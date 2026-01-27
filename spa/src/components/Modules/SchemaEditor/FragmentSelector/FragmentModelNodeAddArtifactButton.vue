<template>
	<div
		v-if="artifactsEnabled"
		class="absolute z-20 right-0 top-1/2 translate-y-4 translate-x-1/2 pointer-events-auto nodrag nopan"
		:class="canClick ? 'cursor-pointer' : 'cursor-default'"
		@click.stop.prevent="canClick && emit('add-artifact')"
	>
		<Handle
			id="source-artifact"
			type="source"
			:position="Position.Right"
			class="!relative !transform-none !bg-transparent !border-0 !w-6 !h-6"
		>
			<div
				class="w-6 h-6 rounded-full flex items-center justify-center transition-colors"
				:class="buttonClasses"
				:title="editEnabled ? 'Add Artifact Category' : ''"
			>
				<QSpinner v-if="loading" color="white" size="12px" />
				<DocumentIcon v-else class="w-3 h-3 text-white" />
			</div>
		</Handle>
	</div>
</template>

<script setup lang="ts">
import { Handle, Position } from "@vue-flow/core";
import { FaSolidFileLines as DocumentIcon } from "danx-icon";
import { QSpinner } from "quasar";
import { computed } from "vue";

const props = defineProps<{
	editEnabled: boolean;
	artifactsEnabled: boolean;
	loading?: boolean;
}>();

const emit = defineEmits<{
	"add-artifact": [];
}>();

const canClick = computed(() => props.editEnabled && !props.loading);

const buttonClasses = computed(() => {
	if (props.loading) return "bg-violet-800";
	if (!props.editEnabled) return "bg-violet-900 opacity-50";
	return "bg-violet-600 hover:bg-violet-500";
});
</script>
