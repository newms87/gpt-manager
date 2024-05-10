import { useActionRoutes } from "quasar-ui-danx";

const API_URL = import.meta.env.VITE_API_URL;

export const WorkflowRoutes = useActionRoutes(API_URL + "/workflows");
export const WorkflowJobsRoutes = useActionRoutes(API_URL + "/jobs");
export const WorkflowRunsRoutes = useActionRoutes(API_URL + "/runs");
