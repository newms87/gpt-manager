<template>
	<div class="space-y-4">
		<!-- Header -->
		<div class="flex items-center justify-between">
			<h3 class="text-lg font-semibold text-slate-200">Template Variables</h3>
			<span class="text-sm text-slate-400">{{ variables.length }} variable{{ variables.length !== 1 ? "s" : "" }}</span>
		</div>

		<!-- Empty state -->
		<div
			v-if="variables.length === 0"
			class="bg-slate-700 rounded-lg p-6 text-center text-slate-400"
		>
			<VariableIcon class="w-8 h-8 mx-auto mb-3 opacity-50" />
			<p>No variables defined yet</p>
			<p class="text-sm mt-1">Variables will be extracted from your template content</p>
		</div>

		<!-- Variable list -->
		<ListTransition>
			<div
				v-for="variable in variables"
				:key="variable.id"
				class="bg-slate-700 rounded-lg overflow-hidden"
			>
				<!-- Variable header -->
				<div
					class="flex items-center p-3 cursor-pointer hover:bg-slate-600/50 transition-colors"
					@click="toggleExpanded(variable.id)"
				>
					<div class="flex-grow">
						<div class="flex items-center">
							<code class="text-sky-400 font-mono text-sm">{{ formatVariableName(variable.name) }}</code>
							<span
								class="ml-2 text-xs px-2 py-0.5 rounded"
								:class="mappingTypeStyles[variable.mapping_type]"
							>
								{{ mappingTypeLabels[variable.mapping_type] }}
							</span>
						</div>
						<div v-if="variable.description" class="text-xs text-slate-400 mt-1">
							{{ variable.description }}
						</div>
					</div>

					<div class="flex items-center gap-2">
						<ActionButton
							type="trash"
							size="xs"
							color="red"
							tooltip="Delete variable"
							@click.stop="$emit('delete', variable)"
						/>
						<ChevronIcon
							class="w-4 h-4 text-slate-400 transition-transform"
							:class="{ 'rotate-180': expandedIds.has(variable.id) }"
						/>
					</div>
				</div>

				<!-- Variable configuration -->
				<div
					v-if="expandedIds.has(variable.id)"
					class="border-t border-slate-600 p-4 space-y-4"
				>
					<!-- Description -->
					<TextField
						:model-value="variable.description || ''"
						label="Description"
						placeholder="Describe this variable's purpose..."
						@update:model-value="value => onUpdate(variable, { description: value })"
					/>

					<!-- Mapping type selector -->
					<SelectField
						:model-value="variable.mapping_type"
						label="Mapping Type"
						:options="mappingTypeOptions"
						@update:model-value="value => onUpdate(variable, { mapping_type: value })"
					/>

					<!-- AI mapping configuration -->
					<template v-if="variable.mapping_type === 'ai'">
						<TextField
							:model-value="variable.ai_prompt || ''"
							label="AI Prompt"
							placeholder="Describe what content should be generated..."
							type="textarea"
							@update:model-value="value => onUpdate(variable, { ai_prompt: value })"
						/>
					</template>

					<!-- Artifact mapping configuration -->
					<template v-else-if="variable.mapping_type === 'artifact'">
						<TextField
							:model-value="variable.artifact_field || ''"
							label="Artifact Field"
							placeholder="Field name in artifact data..."
							@update:model-value="value => onUpdate(variable, { artifact_field: value })"
						/>
						<TextField
							:model-value="variable.artifact_format || ''"
							label="Format (optional)"
							placeholder="Format string for the value..."
							@update:model-value="value => onUpdate(variable, { artifact_format: value })"
						/>
					</template>

					<!-- Team object mapping configuration -->
					<template v-else-if="variable.mapping_type === 'team_object'">
						<SelectField
							:model-value="variable.schema_association_id"
							label="Schema Association"
							:options="schemaAssociationOptions"
							@update:model-value="value => onUpdate(variable, { schema_association_id: value })"
						/>
						<TextField
							:model-value="variable.team_object_field || ''"
							label="Object Field"
							placeholder="Field path in team object..."
							@update:model-value="value => onUpdate(variable, { team_object_field: value })"
						/>
					</template>

					<!-- Default value -->
					<TextField
						:model-value="variable.default_value || ''"
						label="Default Value"
						placeholder="Value to use if mapping fails..."
						@update:model-value="value => onUpdate(variable, { default_value: value })"
					/>
				</div>
			</div>
		</ListTransition>
	</div>
</template>

<script setup lang="ts">
import type { TemplateVariable, VariableMappingType } from "@/ui/templates/types";
import { SchemaAssociation } from "@/types";
import {
	FaSolidChevronDown as ChevronIcon,
	FaSolidCode as VariableIcon
} from "danx-icon";
import { ActionButton, ListTransition, SelectField, TextField } from "quasar-ui-danx";
import { computed, ref } from "vue";

const props = withDefaults(defineProps<{
	variables: TemplateVariable[];
	templateId: number;
	schemaAssociations?: SchemaAssociation[];
}>(), {
	schemaAssociations: () => []
});

const emit = defineEmits<{
	update: [variable: TemplateVariable, updates: Partial<TemplateVariable>];
	delete: [variable: TemplateVariable];
}>();

const expandedIds = ref<Set<number>>(new Set());

/**
 * Mapping type labels for display
 */
const mappingTypeLabels: Record<VariableMappingType, string> = {
	ai: "AI Generated",
	artifact: "From Artifact",
	team_object: "Team Object"
};

/**
 * Mapping type styles for badges
 */
const mappingTypeStyles: Record<VariableMappingType, string> = {
	ai: "bg-purple-700 text-purple-200",
	artifact: "bg-amber-700 text-amber-200",
	team_object: "bg-sky-700 text-sky-200"
};

/**
 * Options for mapping type select
 */
const mappingTypeOptions = [
	{ label: "AI Generated", value: "ai" },
	{ label: "From Artifact", value: "artifact" },
	{ label: "Team Object", value: "team_object" }
];

/**
 * Options for schema association select
 */
const schemaAssociationOptions = computed(() => {
	return props.schemaAssociations.map(sa => ({
		label: sa.name || `Association ${sa.id}`,
		value: sa.id
	}));
});

/**
 * Toggle expanded state for a variable
 */
function toggleExpanded(id: number) {
	if (expandedIds.value.has(id)) {
		expandedIds.value.delete(id);
	} else {
		expandedIds.value.add(id);
	}
}

/**
 * Emit update event for a variable
 */
function onUpdate(variable: TemplateVariable, updates: Partial<TemplateVariable>) {
	emit("update", variable, updates);
}

/**
 * Format variable name with mustache syntax
 */
function formatVariableName(name: string): string {
	return `{{${name}}}`;
}
</script>
