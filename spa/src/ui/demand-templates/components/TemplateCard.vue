<template>
	<div class="template-card bg-slate-800 rounded-lg shadow-md border border-slate-700 p-6 hover:shadow-lg transition-all duration-200">
		<!-- Header with Name and Actions -->
		<div class="flex items-start justify-between mb-4">
			<div class="flex-1">
				<EditableDiv
					:model-value="template.name"
					class="text-xl font-semibold text-slate-100 rounded-sm px-2 py-1 -mx-2 -my-1"
					placeholder="Template Name"
					@update:model-value="updateTemplate({ name: $event })"
				/>
			</div>

			<!-- Actions -->
			<div class="flex items-center gap-2 ml-4">
				<!-- Template Variables Button -->
				<ActionButton
					tooltip="View Template Variables"
					size="sm"
					@click="showVariablesDialog = true"
				>
					<VariablesIcon class="w-4" />
				</ActionButton>

				<!-- Active Toggle -->
				<QToggle
					:model-value="template.is_active"
					color="green"
					size="sm"
					:label="template.is_active ? 'Active' : 'Inactive'"
					@update:model-value="updateTemplate({ is_active: $event })"
				/>

				<!-- Delete Button -->
				<ActionButton
					type="delete"
					tooltip="Delete Template"
					size="sm"
					@click="$emit('delete', template)"
				/>
			</div>
		</div>

		<!-- Description -->
		<div class="mb-4">
			<EditableDiv
				:model-value="template.description || ''"
				class="text-slate-300"
				placeholder="Enter description..."
				@update:model-value="updateTemplate({ description: $event })"
			/>
		</div>

		<!-- Template URL -->
		<div class="mb-4">
			<div class="flex items-center gap-2 mb-2">
				<span class="text-sm font-medium text-slate-300">Template URL:</span>
				<ActionButton
					v-if="template.template_url"
					tooltip="Open Template"
					size="xs"
					@click="openTemplate"
				>
					<OpenIcon class="w-3" />
				</ActionButton>
			</div>
			<UrlEditField
				:model-value="template.template_url || ''"
				:loading="isUpdating"
				@url-saved="onUrlSaved"
			/>
		</div>

		<!-- Metadata -->
		<div class="flex items-center justify-between text-xs text-slate-500 pt-4 border-t border-slate-700">
			<div>
				Created {{ formatDate(template.created_at) }}
			</div>
			<div v-if="template.user">
				by {{ template.user.name }}
			</div>
		</div>

		<!-- Loading Overlay -->
		<div
			v-if="isUpdating"
			class="absolute inset-0 bg-slate-800 bg-opacity-75 flex items-center justify-center rounded-lg"
		>
			<QSpinner color="blue" size="md" />
		</div>

		<!-- Template Variables Dialog -->
		<TemplateVariablesDialog
			:template="template"
			:is-showing="showVariablesDialog"
			@close="showVariablesDialog = false"
		/>
	</div>
</template>

<script setup lang="ts">
import { QSpinner, QToggle } from "quasar";
import { ActionButton, EditableDiv, fDate } from "quasar-ui-danx";
import { 
	FaSolidCodeBranch as VariablesIcon,
	FaSolidUpRightFromSquare as OpenIcon
} from "danx-icon";
import { ref } from "vue";
import type { DemandTemplate } from "../types";
import { useDemandTemplates } from "../composables/useDemandTemplates";
import UrlEditField from "./UrlEditField.vue";
import TemplateVariablesDialog from "./TemplateVariablesDialog.vue";

const props = defineProps<{
	template: DemandTemplate;
}>();

const emit = defineEmits<{
	"update": [template: DemandTemplate, data: Partial<DemandTemplate>];
	"delete": [template: DemandTemplate];
}>();

const { fetchTemplateVariables, mergeTemplateVariables } = useDemandTemplates();

const isUpdating = ref(false);
const showVariablesDialog = ref(false);

const updateTemplate = (data: Partial<DemandTemplate>) => {
	emit("update", props.template, data);
};

const openTemplate = () => {
	if (props.template.template_url) {
		window.open(props.template.template_url, "_blank");
	}
};

const onUrlSaved = async (url: string) => {
	// Update the template URL first
	updateTemplate({ template_url: url });
	
	// Auto-fetch template variables if URL is provided
	if (url && props.template.id) {
		try {
			isUpdating.value = true;
			const fetchedVariables = await fetchTemplateVariables(props.template.id);
			
			// Merge with existing variables to preserve descriptions
			const mergedVariables = mergeTemplateVariables(
				props.template.template_variables || {},
				fetchedVariables || {}
			);
			
			// Update template with merged variables
			updateTemplate({ template_variables: mergedVariables });
		} catch (error) {
			console.error('Failed to auto-fetch template variables:', error);
		} finally {
			isUpdating.value = false;
		}
	}
};

const formatDate = (dateString: string) => {
	return fDate(dateString);
};
</script>

<style scoped>
.template-card {
	position: relative;
}

.template-card:hover .q-btn {
	opacity: 1;
}

.q-btn {
	opacity: 0.7;
	transition: opacity 0.2s;
}
</style>
