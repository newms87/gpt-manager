import { WorkflowRunRoutes } from "@/types";
import { request, useActionRoutes } from "quasar-ui-danx";

const baseUrl = import.meta.env.VITE_API_URL + "/workflow-runs";

export const routes = useActionRoutes(baseUrl, {
	runStatuses(filter) {
		return request.get(`${baseUrl}/run-statuses`, { params: { filter }, abortOn: null });
	}
}) as WorkflowRunRoutes;
