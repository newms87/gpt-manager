<template>
	<div
		v-if="editEnabled && artifactsEnabled"
		class="absolute z-20 right-0 top-1/2 translate-y-4 translate-x-1/2 pointer-events-auto nodrag nopan"
		:class="loading ? '' : 'cursor-pointer'"
		@click.stop.prevent="!loading && emit('add-artifact')"
	>
		<Handle
			id="source-artifact"
			type="source"
			:position="Position.Right"
			class="!relative !transform-none !bg-transparent !border-0 !w-6 !h-6"
		>
			<div
				class="w-6 h-6 rounded-full flex items-center justify-center transition-colors"
				:class="loading ? 'bg-violet-800' : 'bg-violet-600 hover:bg-violet-500'"
				title="Add Artifact Category"
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

defineProps<{
	editEnabled: boolean;
	artifactsEnabled: boolean;
	loading?: boolean;
}>();

const emit = defineEmits<{
	"add-artifact": [];
}>();
</script>
