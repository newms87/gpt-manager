import { dxTemplateDefinition } from "@/ui/templates/config";
import type { TemplateDefinition, TemplateType } from "@/ui/templates/types";
import { ref, shallowRef } from "vue";

const isLoadingTemplates = ref(false);
const templateToLoadDetails = ref<TemplateDefinition | null>(null);
const availableTemplates = shallowRef<TemplateDefinition[]>([]);

/**
 * Load templates list
 */
async function loadTemplates() {
	if (isLoadingTemplates.value) return;
	isLoadingTemplates.value = true;

	try {
		const response = await dxTemplateDefinition.routes.list({ sort: [{ column: "name" }] });
		availableTemplates.value = response.data;
	} finally {
		isLoadingTemplates.value = false;
	}
}

/**
 * Load template details including variables and history
 */
async function loadTemplateDetails(template: TemplateDefinition) {
	templateToLoadDetails.value = template;

	await dxTemplateDefinition.routes.details(template, {
		template_variables: true,
		history: true,
		collaboration_threads: { messages: true }
	});

	// Only indicate loading has stopped if this is still the current template
	if (templateToLoadDetails.value?.id === template.id) {
		templateToLoadDetails.value = null;
	}
}

/**
 * Create a new template
 */
async function createTemplate(type: TemplateType) {
	const createAction = dxTemplateDefinition.getAction("create", {
		onFinish: loadTemplates
	});

	const defaultName = type === "google_docs" ? "New Google Docs Template" : "New HTML Template";

	return await createAction.trigger(null, {
		name: defaultName,
		type,
		is_active: true
	});
}

/**
 * Delete a template
 */
async function deleteTemplate(template: TemplateDefinition) {
	const deleteAction = dxTemplateDefinition.getAction("delete", {
		onFinish: loadTemplates
	});

	return await deleteAction.trigger(template);
}

export function useTemplates() {
	return {
		isLoadingTemplates,
		templateToLoadDetails,
		availableTemplates,
		loadTemplates,
		loadTemplateDetails,
		createTemplate,
		deleteTemplate
	};
}
