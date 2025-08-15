<template>
	<div class="demand-templates-view p-6">
		<!-- Header -->
		<div class="flex items-center justify-between mb-8">
			<div>
				<h1 class="text-3xl font-bold text-gray-900">Demand Templates</h1>
				<p class="text-gray-600 mt-1">Create and manage document templates for your demands</p>
			</div>

			<ActionButton
				type="create"
				label="New Template"
				size="lg"
				:action="createAction"
				:input="newTemplateData"
			/>
		</div>

		<!-- Loading State -->
		<div v-if="dxDemandTemplate.isLoadingList.value" class="flex justify-center py-12">
			<QSpinner size="lg" color="blue" />
		</div>

		<!-- Empty State -->
		<div v-else-if="!templates.length" class="text-center py-12">
			<div class="bg-gray-50 rounded-lg p-8 max-w-md mx-auto">
				<FaSolidClipboard class="w-16 h-16 text-gray-400 mx-auto mb-4" />
				<h3 class="text-lg font-medium text-gray-900 mb-2">No templates yet</h3>
				<p class="text-gray-600 mb-4">Get started by creating your first document template</p>
				<ActionButton
					type="create"
					label="Create Template"
					:action="createAction"
					:input="newTemplateData"
				/>
			</div>
		</div>

		<!-- Templates Grid -->
		<div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
			<TemplateCard
				v-for="template in visibleTemplates"
				:key="template.id"
				:template="template"
				@update="handleTemplateUpdate"
				@delete="handleTemplateDelete"
			/>
		</div>
	</div>
</template>

<script setup lang="ts">
import { FaSolidClipboard } from "danx-icon";
import { QSpinner } from "quasar";
import { ActionButton } from "quasar-ui-danx";
import { computed } from "vue";
import TemplateCard from "../components/TemplateCard.vue";
import { dxDemandTemplate } from "../config";
import type { DemandTemplate } from "../types";

dxDemandTemplate.initialize();

// Actions
const createAction = dxDemandTemplate.getAction("quick-create", { onFinish: dxDemandTemplate.loadList });
const updateAction = dxDemandTemplate.getAction("update");
const deleteAction = dxDemandTemplate.getAction("quick-delete", { onFinish: dxDemandTemplate.loadList });

// Computed properties
const templates = computed(() => {
	const pagedData = dxDemandTemplate.pagedItems.value;
	return pagedData?.data || [];
});

const visibleTemplates = computed(() =>
	templates.value.filter((template: DemandTemplate) => !template.deleted_at)
);

const generateTemplateName = () => {
	const count = templates.value.length + 1;
	return `Template ${count}`;
};

const newTemplateData = computed(() => ({
	name: generateTemplateName(),
	description: "",
	template_url: "",
	is_active: true
}));

// Event handlers
const handleTemplateUpdate = (template: DemandTemplate, data: Partial<DemandTemplate>) => {
	updateAction.trigger(template, data);
};

const handleTemplateDelete = (template: DemandTemplate) => {
	if (confirm(`Are you sure you want to delete "${template.name}"?`)) {
		deleteAction.trigger(template);
	}
};
</script>
