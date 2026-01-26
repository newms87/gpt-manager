<template>
	<div class="flex items-center gap-2">
		<!-- Edit/Select Mode Toggle (only when both modes are enabled) -->
		<button
			v-if="props.selectionEnabled && props.editEnabled"
			class="px-3 py-1.5 text-xs rounded-lg border shadow-lg cursor-pointer transition-colors nodrag nopan"
			:class="props.isEditModeActive
				? 'bg-blue-600/90 border-blue-500 text-white'
				: 'bg-slate-800/90 border-slate-600 text-slate-400 hover:bg-slate-700'"
			@click="emit('update:isEditModeActive', !props.isEditModeActive)"
		>
			{{ props.isEditModeActive ? 'Edit Mode' : 'Select Mode' }}
		</button>

		<!-- Show Properties Toggle (only in by-model mode) -->
		<div
			v-if="props.selectionMode === 'by-model'"
			class="flex items-center gap-2 px-3 py-1.5 rounded-lg border shadow-lg cursor-pointer transition-colors"
			:class="props.showProperties
				? 'bg-sky-900/90 border-sky-600 text-sky-300'
				: 'bg-slate-800/90 border-slate-600 text-slate-400'"
			@click="emit('update:showProperties', !props.showProperties)"
		>
			<PropertiesIcon class="w-4" />
			<span class="text-xs">{{ props.showProperties ? 'Hide Props' : 'Show Props' }}</span>
		</div>
	</div>
</template>

<script setup lang="ts">
import { FaSolidListUl as PropertiesIcon } from "danx-icon";
import { SelectionMode } from "./types";

const props = defineProps<{
	selectionEnabled: boolean;
	editEnabled: boolean;
	isEditModeActive: boolean;
	showProperties: boolean;
	selectionMode: SelectionMode;
}>();

const emit = defineEmits<{
	"update:isEditModeActive": [value: boolean];
	"update:showProperties": [value: boolean];
}>();
</script>
