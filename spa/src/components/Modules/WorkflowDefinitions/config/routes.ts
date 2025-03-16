import { WorkflowDefinition, WorkflowDefinitionRoutes } from "@/types";
import { AnyObject, request, useActionRoutes } from "quasar-ui-danx";

const API_URL = import.meta.env.VITE_API_URL + "/workflow-definitions";

export const routes = useActionRoutes(API_URL, {
	exportToJson: async (workflowDefinition: WorkflowDefinition) => request.get(`${API_URL}/${workflowDefinition.id}/export-to-json`),
	importFromJson: async (workflowDefinitionJson: AnyObject) => request.post(`${API_URL}/import-from-json`, { workflowDefinitionJson })
}) as WorkflowDefinitionRoutes;
