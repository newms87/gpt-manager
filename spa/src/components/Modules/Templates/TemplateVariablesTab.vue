<template>
	<div class="h-full overflow-auto bg-white rounded-lg shadow-lg ring-1 ring-slate-200/50 p-6">
		<!-- Schema selector header -->
		<div class="mb-6">
			<div class="flex items-center justify-between mb-3">
				<label class="text-sm font-semibold text-slate-700">Schema Definition</label>
				<ActionButton
					v-if="template.schema_definition_id"
					:icon="AiIcon"
					label="AI Suggest Mappings"
					color="purple-invert"
					size="sm"
					tooltip="Let AI suggest variable mappings based on the schema"
					:loading="isSuggestingMappings"
					:disabled="!canSuggestMappings"
					@click="handleSuggestMappings"
				/>
			</div>
			<SchemaEditorToolbox
				v-model="selectedSchema"
				:can-select="true"
				:can-select-fragment="false"
				:previewable="true"
				:clearable="true"
				button-color="bg-sky-600"
				placeholder="(Select a schema for variable mappings)"
				@update:model-value="onSchemaChange"
			/>
			<div class="text-xs text-slate-500 mt-1">
				Select a schema to enable AI-assisted variable mapping suggestions.
			</div>
		</div>

		<!-- Variables section -->
		<div class="border-t border-slate-200 pt-6">
			<div class="flex items-center justify-between mb-4">
				<h3 class="text-sm font-semibold text-slate-700">
					Template Variables
					<span v-if="activeVariables.length > 0" class="text-slate-400 font-normal">
						({{ activeVariables.length }})
					</span>
				</h3>
			</div>

			<!-- Empty state -->
			<div
				v-if="activeVariables.length === 0"
				class="bg-slate-50 rounded-lg p-6 text-center text-slate-500"
			>
				<VariableIcon class="w-8 h-8 mx-auto mb-3 opacity-50" />
				<p class="font-medium">No variables defined</p>
				<p class="text-sm mt-1">Variables will be extracted from your template's HTML content using <code class="text-sky-600">data-var-*</code> attributes.</p>
			</div>

			<!-- Active variables list -->
			<div v-else class="space-y-2">
				<TemplateVariableListItem
					v-for="variable in activeVariables"
					:key="variable.id"
					:variable="variable"
					@click="openVariableEditor"
				/>
			</div>
		</div>

		<!-- Removed variables section -->
		<div v-if="removedVariables.length > 0" class="border-t border-slate-200 pt-6 mt-6">
			<ShowHideButton
				v-model="showRemovedVariables"
				class="text-slate-500 hover:text-slate-700"
			>
				<template #default="{ isShowing }">
					<span class="text-sm font-medium">
						Removed Variables ({{ removedVariables.length }})
						<span class="text-xs text-slate-400 ml-1">{{ isShowing ? 'Hide' : 'Show' }}</span>
					</span>
				</template>
			</ShowHideButton>

			<div v-if="showRemovedVariables" class="mt-3 space-y-2 opacity-60">
				<TemplateVariableListItem
					v-for="variable in removedVariables"
					:key="variable.id"
					:variable="variable"
					@click="openVariableEditor"
				/>
			</div>
		</div>

		<!-- Variable editor dialog -->
		<TemplateVariableEditor
			v-if="editingVariable"
			:variable="editingVariable"
			:template-id="template.id"
			@close="editingVariable = null"
			@save="onVariableSave"
		/>

		<!-- AI Mapping Suggestions Dialog -->
		<AiMappingSuggestionsDialog
			v-model="showSuggestionsDialog"
			:suggestions="suggestions"
			@accept="onAcceptSuggestions"
		/>
	</div>
</template>

<script setup lang="ts">
import SchemaEditorToolbox from "@/components/Modules/SchemaEditor/SchemaEditorToolbox.vue";
import AiMappingSuggestionsDialog, { MappingSuggestion } from "@/components/Modules/Templates/AiMappingSuggestionsDialog.vue";
import TemplateVariableListItem from "@/components/Modules/Templates/TemplateVariableListItem.vue";
import TemplateVariableEditor from "@/ui/templates/components/TemplateVariableEditor.vue";
import { dxTemplateDefinition } from "@/ui/templates/config";
import type { TemplateDefinition, TemplateVariable } from "@/ui/templates/types";
import type { SchemaDefinition } from "@/types";
import {
	FaSolidBrain as AiIcon,
	FaSolidCode as VariableIcon
} from "danx-icon";
import { ActionButton, ShowHideButton } from "quasar-ui-danx";
import { computed, ref, watch } from "vue";

const props = defineProps<{
	template: TemplateDefinition;
	variables: TemplateVariable[];
}>();

const emit = defineEmits<{
	"update-schema": [schemaId: number | null];
	"update-variable": [variable: TemplateVariable];
}>();

// Local state
const selectedSchema = ref<SchemaDefinition | null>(null);
const editingVariable = ref<TemplateVariable | null>(null);
const showRemovedVariables = ref(false);

// AI suggestion state
const isSuggestingMappings = ref(false);
const showSuggestionsDialog = ref(false);
const suggestions = ref<MappingSuggestion[]>([]);

/**
 * Active (non-deleted) variables
 */
const activeVariables = computed(() => {
	return props.variables.filter(v => !v.deleted_at);
});

/**
 * Soft-deleted/removed variables
 */
const removedVariables = computed(() => {
	return props.variables.filter(v => v.deleted_at);
});

/**
 * Unmapped variables (no schema association)
 */
const unmappedVariables = computed(() => {
	return activeVariables.value.filter(v => !v.team_object_schema_association_id);
});

/**
 * Can suggest mappings only if schema is selected and there are unmapped variables
 */
const canSuggestMappings = computed(() => {
	return props.template.schema_definition_id && unmappedVariables.value.length > 0;
});

/**
 * Initialize selected schema from template's relationship
 */
watch(
	() => props.template.schema_definition,
	(schemaDefinition) => {
		selectedSchema.value = schemaDefinition || null;
	},
	{ immediate: true }
);

/**
 * Handle schema selection change
 */
function onSchemaChange(schema: SchemaDefinition | null) {
	emit("update-schema", schema?.id ?? null);
}

/**
 * Open variable editor dialog
 */
function openVariableEditor(variable: TemplateVariable) {
	editingVariable.value = variable;
}

/**
 * Handle variable save from editor
 */
function onVariableSave(variable: TemplateVariable) {
	emit("update-variable", variable);
	editingVariable.value = null;
}

/**
 * Handle AI suggest mappings button click
 * Calls the backend action and shows results in a dialog
 */
async function handleSuggestMappings() {
	if (!canSuggestMappings.value || isSuggestingMappings.value) return;

	isSuggestingMappings.value = true;

	try {
		const suggestMappingsAction = dxTemplateDefinition.getAction("suggest-mappings");
		const result = await suggestMappingsAction.trigger(props.template, {});

		// Extract suggestions from the action result
		if (result?.result?.suggestions) {
			suggestions.value = result.result.suggestions;
			showSuggestionsDialog.value = true;
		} else {
			// No suggestions returned
			suggestions.value = [];
			showSuggestionsDialog.value = true;
		}
	} finally {
		isSuggestingMappings.value = false;
	}
}

/**
 * Handle accepting suggestions from the dialog
 * Emits update-variable for each accepted suggestion
 */
function onAcceptSuggestions(acceptedSuggestions: MappingSuggestion[]) {
	for (const suggestion of acceptedSuggestions) {
		// Find the variable in our list
		const variable = props.variables.find(v => v.id === suggestion.variable_id);
		if (variable && suggestion.suggested_fragment_id) {
			// Emit update with schema_definition_id and schema_fragment_id
			// The backend will create a SchemaAssociation for the variable
			emit("update-variable", {
				...variable,
				mapping_type: "team_object",
				schema_definition_id: props.template.schema_definition_id,
				schema_fragment_id: suggestion.suggested_fragment_id
			});
		}
	}
}
</script>
