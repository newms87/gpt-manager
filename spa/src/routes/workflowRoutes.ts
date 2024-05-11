import { useActionRoutes } from "quasar-ui-danx";

const API_URL = import.meta.env.VITE_API_URL;

export const WorkflowRoutes = useActionRoutes(API_URL + "/workflows");
export const WorkflowJobRoutes = useActionRoutes(API_URL + "/workflow-jobs");
export const WorkflowRunRoutes = useActionRoutes(API_URL + "/workflow-runs");
export const WorkflowAssignmentRoutes = useActionRoutes(API_URL + "/workflow-assignments");
