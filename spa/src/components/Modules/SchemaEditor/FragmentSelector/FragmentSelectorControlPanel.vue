<template>
	<div class="flex items-center gap-1">
		<!-- Edit/Select Mode Toggle (only when both modes are enabled) -->
		<button
			v-if="props.selectionEnabled && props.editEnabled"
			class="flex items-center justify-center w-8 h-8 rounded-lg border shadow-lg cursor-pointer transition-colors nodrag nopan bg-slate-800/90 border-slate-600 text-slate-400 hover:bg-slate-700"
			:title="props.isEditModeActive ? 'Switch to Select Mode' : 'Switch to Edit Mode'"
			@click="emit('update:isEditModeActive', !props.isEditModeActive)"
		>
			<component :is="props.isEditModeActive ? SelectIcon : EditIcon" class="w-4" />
		</button>

		<!-- Code Sidebar Toggle (only when selection or edit mode is active) -->
		<button
			v-if="props.selectionEnabled || props.editEnabled"
			class="flex items-center justify-center w-8 h-8 rounded-lg border shadow-lg cursor-pointer transition-colors nodrag nopan"
			:class="props.showCode
				? 'bg-sky-900/90 border-sky-600 text-sky-300'
				: 'bg-slate-800/90 border-slate-600 text-slate-400 hover:bg-slate-700'"
			:title="props.showCode ? 'Hide Code' : 'Show Code'"
			@click="emit('update:showCode', !props.showCode)"
		>
			<CodeIcon class="w-4" />
		</button>

		<!-- Show Properties Toggle (only in by-model mode) -->
		<div
			v-if="props.selectionMode === 'by-model'"
			class="flex items-center gap-2 px-3 py-1.5 rounded-lg border shadow-lg cursor-pointer transition-colors whitespace-nowrap"
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
import { FaSolidCode as CodeIcon, FaSolidListCheck as SelectIcon, FaSolidListUl as PropertiesIcon, FaSolidPencil as EditIcon } from "danx-icon";
import { SelectionMode } from "./types";

const props = defineProps<{
	selectionEnabled: boolean;
	editEnabled: boolean;
	isEditModeActive: boolean;
	showProperties: boolean;
	showCode: boolean;
	selectionMode: SelectionMode;
}>();

const emit = defineEmits<{
	"update:isEditModeActive": [value: boolean];
	"update:showProperties": [value: boolean];
	"update:showCode": [value: boolean];
}>();
</script>
