import { WorkflowDefinition, WorkflowDefinitionRoutes } from "@/types";
import { request, useActionRoutes } from "quasar-ui-danx";

const API_URL = import.meta.env.VITE_API_URL + "/workflow-definitions";

export const routes = useActionRoutes(API_URL, {
	exportToJson: async (workflowDefinition: WorkflowDefinition) => request.get(`${API_URL}/${workflowDefinition.id}/export-to-json`)
}) as WorkflowDefinitionRoutes;
