<template>
	<div class="template-card bg-white rounded-lg shadow-md border border-slate-200 p-6 hover:shadow-lg transition-all duration-200 w-full">
		<!-- Header with Name and Actions -->
		<div class="flex items-start justify-between mb-4">
			<div class="flex-1">
				<EditableDiv
					:model-value="template.name"
					class="text-xl font-semibold text-slate-800 rounded-sm px-2 py-1 -mx-2 -my-1"
					placeholder="Template Name"
					@update:model-value="updateTemplate({ name: $event })"
				/>
			</div>

			<!-- Actions -->
			<div class="flex items-center gap-2 ml-4">
				<!-- Template Variables Button -->
				<ActionButton
					:icon="VariablesIcon"
					tooltip="View Template Variables"
					size="sm"
					color="blue"
					@click="showVariablesDialog = true"
				/>

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
					type="trash"
					tooltip="Delete Template"
					size="sm"
					color="red"
					@click="$emit('delete', template)"
				/>
			</div>
		</div>

		<!-- Description -->
		<div class="mb-4">
			<EditableDiv
				:model-value="template.description || ''"
				class="text-slate-600"
				placeholder="Enter description..."
				@update:model-value="updateTemplate({ description: $event })"
			/>
		</div>

		<!-- Template URL -->
		<div class="mb-4">
			<div class="flex items-center gap-2 mb-2">
				<span class="text-sm font-medium text-slate-700">Template URL:</span>
				<ActionButton
					v-if="template.template_url"
					:icon="OpenIcon"
					tooltip="Open Template"
					size="xs"
					color="sky"
					@click="openTemplate"
				/>
			</div>
			<UrlEditField
				:model-value="template.template_url || ''"
				:saving="isUpdating"
				@url-saved="onUrlSaved"
			/>
		</div>

		<!-- Metadata -->
		<div class="flex items-center justify-between text-xs text-slate-500 pt-4 border-t border-slate-200">
			<div>
				Created {{ formatDate(template.created_at) }}
			</div>
			<div v-if="template.user" class="font-medium text-slate-600">
				by {{ template.user.name }}
			</div>
		</div>

		<!-- Loading Overlay -->
		<div
			v-if="isUpdating"
			class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center rounded-lg"
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
import { dxDemandTemplate } from "../config";
import UrlEditField from "./UrlEditField.vue";
import TemplateVariablesDialog from "./TemplateVariablesDialog.vue";

const props = defineProps<{
	template: DemandTemplate;
}>();

const emit = defineEmits<{
	"update": [template: DemandTemplate, data: Partial<DemandTemplate>];
	"delete": [template: DemandTemplate];
}>();

// Actions
const fetchVariablesAction = dxDemandTemplate.getAction("fetch-template-variables");
const updateAction = dxDemandTemplate.getAction("update");

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
	if (url && props.template) {
		try {
			isUpdating.value = true;
			// Backend handles merging and returns complete updated template
			await fetchVariablesAction.trigger(props.template);
			// The template object is reactive and will automatically update
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
