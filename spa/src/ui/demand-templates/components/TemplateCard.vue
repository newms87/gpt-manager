<template>
	<div class="template-card bg-white rounded-lg shadow-md border border-gray-200 p-6 hover:shadow-lg transition-all duration-200">
		<!-- Header with Name and Actions -->
		<div class="flex items-start justify-between mb-4">
			<div class="flex-1">
				<EditableDiv
					:model-value="template.name"
					class="text-xl font-semibold text-gray-900 rounded-sm px-2 py-1 -mx-2 -my-1"
					placeholder="Template Name"
					@update:model-value="updateTemplate({ name: $event })"
				/>
			</div>

			<!-- Actions -->
			<div class="flex items-center gap-2 ml-4">
				<!-- Active Toggle -->
				<QToggle
					:model-value="template.is_active"
					color="green"
					size="sm"
					:label="template.is_active ? 'Active' : 'Inactive'"
					@update:model-value="updateTemplate({ is_active: $event })"
				/>

				<!-- Delete Button -->
				<QBtn
					icon="delete"
					color="red"
					flat
					round
					size="sm"
					@click="$emit('delete', template)"
				>
					<QTooltip>Delete Template</QTooltip>
				</QBtn>
			</div>
		</div>

		<!-- Description -->
		<div class="mb-4">
			<EditableDiv
				:model-value="template.description || ''"
				placeholder="Enter description..."
				@update:model-value="updateTemplate({ description: $event })"
			/>
		</div>

		<!-- Template URL -->
		<div class="mb-4">
			<div class="flex items-center gap-2 mb-2">
				<span class="text-sm font-medium text-gray-700">Template URL:</span>
			</div>
			<div class="flex items-center gap-2 overflow-hidden">
				<EditableDiv
					:model-value="template.template_url || ''"
					class="max-w-full w-full rounded-lg overflow-hidden"
					content-class="px-2 py-1 w-full"
					placeholder="https://docs.google.com/document/d/..."
					@update:model-value="updateTemplate({ template_url: $event })"
				/>
				<QBtn
					v-if="template.template_url"
					icon="open_in_new"
					color="blue"
					flat
					round
					size="sm"
					@click="openTemplate"
				>
					<QTooltip>Open Template</QTooltip>
				</QBtn>
			</div>
		</div>

		<!-- Metadata -->
		<div class="flex items-center justify-between text-xs text-gray-500 pt-4 border-t border-gray-100">
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
			class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center rounded-lg"
		>
			<QSpinner color="blue" size="md" />
		</div>
	</div>
</template>

<script setup lang="ts">
import { QBtn, QSpinner, QToggle, QTooltip } from "quasar";
import { EditableDiv, fDate } from "quasar-ui-danx";
import { ref } from "vue";
import type { DemandTemplate } from "../types";

const props = defineProps<{
	template: DemandTemplate;
}>();

const emit = defineEmits<{
	"update": [template: DemandTemplate, data: Partial<DemandTemplate>];
	"delete": [template: DemandTemplate];
}>();

const isUpdating = ref(false);

const updateTemplate = (data: Partial<DemandTemplate>) => {
	emit("update", props.template, data);
};

const openTemplate = () => {
	if (props.template.template_url) {
		window.open(props.template.template_url, "_blank");
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
