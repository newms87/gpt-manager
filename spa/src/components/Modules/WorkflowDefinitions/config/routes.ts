import { apiUrls } from "@/api";
import { WorkflowDefinition, WorkflowDefinitionRoutes } from "@/types";
import { AnyObject, request, useActionRoutes } from "quasar-ui-danx";

export const routes = useActionRoutes(apiUrls.workflows.definitions, {
	exportToJson: async (workflowDefinition: WorkflowDefinition) => request.get(`${apiUrls.workflows.definitions}/${workflowDefinition.id}/export-to-json`),
	importFromJson: async (workflowDefinitionJson: AnyObject) => request.post(`${apiUrls.workflows.definitions}/import-from-json`, { workflowDefinitionJson })
}) as WorkflowDefinitionRoutes;
