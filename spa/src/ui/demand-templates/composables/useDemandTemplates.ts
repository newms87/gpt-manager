import { computed } from "vue";
import { dxDemandTemplate } from "../config";
import type { DemandTemplate } from "../types";

export function useDemandTemplates() {
	// Actions
	const createAction = dxDemandTemplate.getAction("create");
	const updateAction = dxDemandTemplate.getAction("update");
	const deleteAction = dxDemandTemplate.getAction("delete");
	const fetchTemplateVariablesAction = dxDemandTemplate.getAction("fetch-template-variables");

	// Computed properties
	const templates = computed(() => {
		return dxDemandTemplate.pagedItems.value?.data || [];
	});

	const activeTemplates = computed(() =>
			templates.value.filter((t: DemandTemplate) => t.is_active)
	);

	const sortedTemplates = computed(() =>
			[...templates.value].sort((a: DemandTemplate, b: DemandTemplate) =>
					new Date(b.updated_at).getTime() - new Date(a.updated_at).getTime()
			)
	);

	const isLoading = computed(() => dxDemandTemplate.isLoadingList.value);

	// Methods
	const loadTemplates = () => {
		dxDemandTemplate.loadList();
	};

	const loadActiveTemplates = () => {
		dxDemandTemplate.initialize();
		dxDemandTemplate.setActiveFilter({ is_active: true });
		dxDemandTemplate.loadList();
	};

	const createTemplate = (data: Partial<DemandTemplate>) => {
		return createAction.trigger(null, data);
	};

	const updateTemplate = (template: DemandTemplate, data: Partial<DemandTemplate>) => {
		return updateAction.trigger(template, data);
	};

	const deleteTemplate = (template: DemandTemplate) => {
		return deleteAction.trigger(template);
	};

	const toggleActive = (template: DemandTemplate) => {
		return updateAction.trigger(template, { is_active: !template.is_active });
	};

	const fetchTemplateVariables = async (templateId: number) => {
		return fetchTemplateVariablesAction.trigger(templateId, {});
	};

	const mergeTemplateVariables = (
			existingVariables: Record<string, string> = {},
			fetchedVariables: Record<string, string> = {}
	): Record<string, string> => {
		const merged: Record<string, string> = { ...existingVariables };

		// Add new variables from fetched data, preserving existing descriptions
		Object.keys(fetchedVariables).forEach(key => {
			if (!(key in merged)) {
				merged[key] = fetchedVariables[key] || "";
			}
		});

		return merged;
	};

	return {
		templates: sortedTemplates,
		activeTemplates,
		isLoading,
		loadTemplates,
		loadActiveTemplates,
		createTemplate,
		updateTemplate,
		deleteTemplate,
		toggleActive,
		fetchTemplateVariables,
		mergeTemplateVariables
	};
}
