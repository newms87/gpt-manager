import { apiUrls } from "@/api";
import { useActionRoutes } from "quasar-ui-danx";
import { ref } from "vue";
import type { TemplateVariable } from "../types";

export function useTemplateVariables() {
    const routes = useActionRoutes(apiUrls.templates.variables);
    const isLoading = ref(false);
    const error = ref<string | null>(null);

    const updateVariable = async (variableId: number, data: Partial<TemplateVariable>) => {
        try {
            isLoading.value = true;
            error.value = null;
            return await routes.applyAction("update", { id: variableId }, data);
        } catch (err: any) {
            error.value = err.message || "Failed to update variable";
            throw err;
        } finally {
            isLoading.value = false;
        }
    };

    return {
        routes,
        isLoading,
        error,
        updateVariable
    };
}
