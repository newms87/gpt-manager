<template>
	<div
		class="bg-white rounded-lg border border-slate-200 p-3 cursor-pointer hover:border-sky-300 hover:shadow-sm transition-all"
		@click="emit('click', variable)"
	>
		<div class="flex items-start gap-3">
			<!-- Variable name and type -->
			<div class="flex-grow min-w-0">
				<div class="flex items-center gap-2 flex-wrap">
					<code class="text-sky-600 font-mono text-sm font-medium">{{ formatVariableName(variable.name) }}</code>
					<span
						class="text-xs px-2 py-0.5 rounded-full font-medium"
						:class="mappingTypeStyles[variable.mapping_type]"
					>
						{{ mappingTypeLabels[variable.mapping_type] }}
					</span>
				</div>

				<!-- Description -->
				<div
					v-if="variable.description"
					class="text-xs text-slate-500 mt-1 line-clamp-2"
				>
					{{ variable.description }}
				</div>

				<!-- Schema fragment info -->
				<div
					v-if="variable.mapping_type === 'team_object' && schemaFragmentName"
					class="flex items-center gap-1 mt-1.5 text-xs text-green-600"
				>
					<FragmentIcon class="w-3" />
					<span>{{ schemaFragmentName }}</span>
				</div>
			</div>

			<!-- Chevron indicator -->
			<ChevronIcon class="w-3 h-3 text-slate-400 flex-shrink-0 mt-1" />
		</div>
	</div>
</template>

<script setup lang="ts">
import type { TemplateVariable, VariableMappingType } from "@/ui/templates/types";
import {
	FaSolidChevronRight as ChevronIcon,
	FaSolidPuzzlePiece as FragmentIcon
} from "danx-icon";
import { computed } from "vue";

const props = defineProps<{
	variable: TemplateVariable;
}>();

const emit = defineEmits<{
	click: [variable: TemplateVariable];
}>();

/**
 * Mapping type labels for display
 */
const mappingTypeLabels: Record<VariableMappingType, string> = {
	ai: "AI",
	artifact: "Artifact",
	team_object: "Team Object"
};

/**
 * Mapping type styles for badges
 */
const mappingTypeStyles: Record<VariableMappingType, string> = {
	ai: "bg-purple-100 text-purple-700",
	artifact: "bg-amber-100 text-amber-700",
	team_object: "bg-green-100 text-green-700"
};

/**
 * Get the schema fragment name if available
 */
const schemaFragmentName = computed(() => {
	if (props.variable.schema_association?.schema_definition?.name) {
		return props.variable.schema_association.schema_definition.name;
	}
	return null;
});

/**
 * Format variable name with mustache syntax
 */
function formatVariableName(name: string): string {
	return `{{${name}}}`;
}
</script>
