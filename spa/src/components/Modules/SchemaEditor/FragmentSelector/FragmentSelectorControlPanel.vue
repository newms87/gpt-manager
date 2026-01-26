<template>
	<div class="flex items-center gap-1">
		<!-- Edit/Select Mode Toggle (only when both modes are enabled) -->
		<button
			v-if="props.modes.selectionEnabled.value && props.modes.editEnabled.value"
			class="flex items-center justify-center w-8 h-8 rounded-lg border shadow-lg cursor-pointer transition-colors nodrag nopan bg-slate-800/90 border-slate-600 text-slate-400 hover:bg-slate-700"
			:title="props.modes.isEditModeActive.value ? 'Switch to Select Mode' : 'Switch to Edit Mode'"
			@click="props.modes.isEditModeActive.value = !props.modes.isEditModeActive.value"
		>
			<component :is="props.modes.isEditModeActive.value ? SelectIcon : EditIcon" class="w-4" />
		</button>

		<!-- Code Sidebar Toggle (only when selection or edit mode is active) -->
		<button
			v-if="props.modes.selectionEnabled.value || props.modes.editEnabled.value"
			class="flex items-center justify-center w-8 h-8 rounded-lg border shadow-lg cursor-pointer transition-colors nodrag nopan"
			:class="props.modes.showCodeSidebar.value
				? 'bg-sky-900/90 border-sky-600 text-sky-300'
				: 'bg-slate-800/90 border-slate-600 text-slate-400 hover:bg-slate-700'"
			:title="props.modes.showCodeSidebar.value ? 'Hide Code' : 'Show Code'"
			@click="props.modes.toggleShowCode()"
		>
			<CodeIcon class="w-4" />
		</button>

		<!-- Show Properties Toggle (only in by-model mode) -->
		<div
			v-if="props.modes.selectionMode.value === 'by-model'"
			class="flex items-center gap-2 px-3 py-1.5 rounded-lg border shadow-lg cursor-pointer transition-colors whitespace-nowrap"
			:class="props.modes.showPropertiesInternal.value
				? 'bg-sky-900/90 border-sky-600 text-sky-300'
				: 'bg-slate-800/90 border-slate-600 text-slate-400'"
			@click="props.modes.toggleShowProperties()"
		>
			<PropertiesIcon class="w-4" />
			<span class="text-xs">{{ props.modes.showPropertiesInternal.value ? 'Hide Props' : 'Show Props' }}</span>
		</div>
	</div>
</template>

<script setup lang="ts">
import { FaSolidCode as CodeIcon, FaSolidListCheck as SelectIcon, FaSolidListUl as PropertiesIcon, FaSolidPencil as EditIcon } from "danx-icon";
import { FragmentSelectorModesResult } from "./useFragmentSelectorModes";

const props = defineProps<{
	modes: FragmentSelectorModesResult;
}>();
</script>
