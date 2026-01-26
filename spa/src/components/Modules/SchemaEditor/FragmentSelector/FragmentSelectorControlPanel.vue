<template>
	<div class="flex items-center gap-1">
		<!-- Mode Toggle Buttons -->
		<div v-if="props.modes.editEnabled.value || props.modes.selectionEnabled.value" class="flex">
			<!-- When both modes available: radio button group -->
			<template v-if="props.modes.editEnabled.value && props.modes.selectionEnabled.value">
				<!-- Edit Button (left side of group) -->
				<button
					class="flex items-center justify-center w-8 h-8 rounded-l-lg border-y border-l shadow-lg cursor-pointer transition-colors nodrag nopan"
					:class="props.modes.isEditModeActive.value === true
						? 'bg-sky-900 border-sky-600 text-sky-300'
						: 'bg-slate-800/90 border-slate-600 text-slate-400 hover:bg-slate-700'"
					title="Edit Mode"
					@click="props.modes.isEditModeActive.value = props.modes.isEditModeActive.value === true ? null : true"
				>
					<EditIcon class="w-4" />
				</button>
				<!-- Select Button (right side of group) -->
				<button
					class="flex items-center justify-center w-8 h-8 rounded-r-lg border shadow-lg cursor-pointer transition-colors nodrag nopan"
					:class="props.modes.isEditModeActive.value === false
						? 'bg-sky-900 border-sky-600 text-sky-300'
						: 'bg-slate-800/90 border-slate-600 text-slate-400 hover:bg-slate-700'"
					title="Select Mode"
					@click="props.modes.isEditModeActive.value = props.modes.isEditModeActive.value === false ? null : false"
				>
					<SelectIcon class="w-4" />
				</button>
			</template>

			<!-- When only edit enabled: single toggle button -->
			<button
				v-else-if="props.modes.editEnabled.value"
				class="flex items-center justify-center w-8 h-8 rounded-lg border shadow-lg cursor-pointer transition-colors nodrag nopan"
				:class="props.modes.effectiveEditEnabled.value
					? 'bg-sky-900 border-sky-600 text-sky-300'
					: 'bg-slate-800/90 border-slate-600 text-slate-400 hover:bg-slate-700'"
				title="Toggle Edit Mode"
				@click="props.modes.isEditModeActive.value = !props.modes.isEditModeActive.value"
			>
				<EditIcon class="w-4" />
			</button>

			<!-- When only select enabled: single toggle button -->
			<button
				v-else-if="props.modes.selectionEnabled.value"
				class="flex items-center justify-center w-8 h-8 rounded-lg border shadow-lg cursor-pointer transition-colors nodrag nopan"
				:class="props.modes.effectiveSelectionEnabled.value
					? 'bg-sky-900 border-sky-600 text-sky-300'
					: 'bg-slate-800/90 border-slate-600 text-slate-400 hover:bg-slate-700'"
				title="Toggle Select Mode"
				@click="props.modes.isEditModeActive.value = !props.modes.isEditModeActive.value"
			>
				<SelectIcon class="w-4" />
			</button>
		</div>

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
