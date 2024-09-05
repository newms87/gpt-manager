import { Workflow } from "@/types";
import { download, ListControlsRoutes, request, useActionRoutes } from "quasar-ui-danx";

const API_URL = import.meta.env.VITE_API_URL;

export interface WorkflowListControlRoutes extends ListControlsRoutes {
	exportAsJson: (workflow: Workflow) => Promise<string>;
}

export const routes = useActionRoutes(API_URL + "/workflows", {
	exportAsJson: async (workflow: Workflow) => {
		const response = await request.get(`${API_URL}/workflows/${workflow.id}/export-as-json`);
		return download(JSON.stringify(response), `${workflow.name}.json`);
	}
}) as WorkflowListControlRoutes;

export const WorkflowJobRoutes = useActionRoutes(API_URL + "/workflow-jobs");
export const WorkflowAssignmentRoutes = useActionRoutes(API_URL + "/workflow-assignments");
