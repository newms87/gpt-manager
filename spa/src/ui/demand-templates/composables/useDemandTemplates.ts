import { computed } from "vue";
import { dxDemandTemplate } from "../config";
import type { DemandTemplate } from "../types";

export function useDemandTemplates() {
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

	// Utility methods for convenience
	const loadActiveTemplates = () => {
		dxDemandTemplate.initialize();
		dxDemandTemplate.setActiveFilter({ is_active: true });
		dxDemandTemplate.loadList();
	};

	return {
		templates: sortedTemplates,
		activeTemplates,
		isLoading,
		loadActiveTemplates
	};
}
