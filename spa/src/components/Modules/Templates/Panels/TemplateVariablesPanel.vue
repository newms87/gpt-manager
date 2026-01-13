<template>
	<div class="p-4">
		<TemplateVariableEditor
			:variables="template.template_variables || []"
			:template-id="template.id"
			:schema-associations="schemaAssociations"
			@update="onVariableUpdate"
			@delete="onVariableDelete"
		/>
	</div>
</template>

<script setup lang="ts">
import TemplateVariableEditor from "@/components/Modules/Templates/TemplateVariableEditor.vue";
import type { TemplateDefinition, TemplateVariable } from "@/ui/templates/types";
import { apiUrls } from "@/api";
import { SchemaAssociation } from "@/types";
import { request } from "quasar-ui-danx";
import { onMounted, ref } from "vue";

const props = defineProps<{
	template: TemplateDefinition;
}>();

const schemaAssociations = ref<SchemaAssociation[]>([]);

/**
 * Load schema associations for team object mapping
 */
async function loadSchemaAssociations() {
	try {
		const response = await request.get(apiUrls.schemas.associations);
		schemaAssociations.value = response.data || [];
	} catch (error) {
		console.error("Failed to load schema associations:", error);
	}
}

/**
 * Handle variable update
 */
async function onVariableUpdate(variable: TemplateVariable, updates: Partial<TemplateVariable>) {
	try {
		await request.patch(`${apiUrls.templates.variables}/${variable.id}`, updates);
	} catch (error) {
		console.error("Failed to update variable:", error);
	}
}

/**
 * Handle variable delete
 */
async function onVariableDelete(variable: TemplateVariable) {
	try {
		await request.delete(`${apiUrls.templates.variables}/${variable.id}`);
	} catch (error) {
		console.error("Failed to delete variable:", error);
	}
}

onMounted(() => {
	loadSchemaAssociations();
});
</script>
