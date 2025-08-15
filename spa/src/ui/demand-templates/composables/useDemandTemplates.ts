import { computed } from "vue";
import { dxDemandTemplate } from "../config";
import type { DemandTemplate } from "../types";

export function useDemandTemplates() {
	// Actions
	const createAction = dxDemandTemplate.getAction("create");
	const updateAction = dxDemandTemplate.getAction("update");
	const deleteAction = dxDemandTemplate.getAction("delete");

	// Computed properties
	const templates = computed(() => {
		const pagedData = dxDemandTemplate.pagedItems.value;
		return pagedData?.data || [];
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

	return {
		templates: sortedTemplates,
		activeTemplates,
		isLoading,
		loadTemplates,
		loadActiveTemplates,
		createTemplate,
		updateTemplate,
		deleteTemplate,
		toggleActive
	};
}
