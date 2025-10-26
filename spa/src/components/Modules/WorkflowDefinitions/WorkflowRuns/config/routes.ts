import { apiUrls } from "@/api";
import { WorkflowRunRoutes } from "@/types";
import { request, useActionRoutes } from "quasar-ui-danx";

export const routes = useActionRoutes(apiUrls.workflows.runs, {
	runStatuses: async () => await request.get(apiUrls.workflows.runs),
	errorsUrl: (workflowRun) => `${apiUrls.workflows.runs}/${workflowRun.id}/errors`
}) as WorkflowRunRoutes;
