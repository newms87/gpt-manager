import { computed, ref } from "vue";
import { dxTemplateDefinition } from "../config";
import type { TemplateDefinition } from "../types";

export function useTemplateDefinitions() {
    // Computed properties
    const templates = ref([]);

    const activeTemplates = computed(() =>
        templates.value.filter((t: TemplateDefinition) => t.is_active)
    );

    const sortedTemplates = computed(() =>
        [...templates.value].sort((a: TemplateDefinition, b: TemplateDefinition) =>
            new Date(b.updated_at).getTime() - new Date(a.updated_at).getTime()
        )
    );

    const isLoading = ref(false);

    // Utility methods for convenience
    const loadActiveTemplates = async () => {
        isLoading.value = true;
        try {
            templates.value = (await dxTemplateDefinition.routes.list({ fields: { template_variables: true } }))?.data || [];
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

/**
 * @deprecated Use useTemplateDefinitions instead
 */
export const useDemandTemplates = useTemplateDefinitions;
