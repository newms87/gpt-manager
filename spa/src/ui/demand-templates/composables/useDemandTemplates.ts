import { computed, ref } from "vue";
import { dxDemandTemplate } from "../config";
import type { DemandTemplate } from "../types";

export function useDemandTemplates() {
    // Computed properties
    const templates = ref([]);

    const activeTemplates = computed(() =>
        templates.value.filter((t: DemandTemplate) => t.is_active)
    );

    const sortedTemplates = computed(() =>
        [...templates.value].sort((a: DemandTemplate, b: DemandTemplate) =>
            new Date(b.updated_at).getTime() - new Date(a.updated_at).getTime()
        )
    );

    const isLoading = ref(false);

    // Utility methods for convenience
    const loadActiveTemplates = async () => {
        isLoading.value = true;
        try {
            templates.value = (await dxDemandTemplate.routes.list())?.data || [];
        } finally {
            isLoading.value = false;
        }
    };

    return {
        templates: sortedTemplates,
        activeTemplates,
        isLoading,
        loadActiveTemplates
    };
}
