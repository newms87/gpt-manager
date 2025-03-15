import { WorkflowRunRoutes } from "@/types";
import { request, useActionRoutes } from "quasar-ui-danx";

const API_URL = import.meta.env.VITE_API_URL + "/workflow-runs";

export const routes = useActionRoutes(API_URL, {
	runStatuses: async () => await request.get(API_URL)
}) as WorkflowRunRoutes;
