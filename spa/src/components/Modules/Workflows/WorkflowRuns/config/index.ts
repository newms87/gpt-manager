import { WorkflowRun, WorkflowRunRoutes } from "@/types";
import { ActionController } from "quasar-ui-danx";
import { actionControls } from "./actions";
import { routes } from "./routes";

export const dxWorkflowRun = {
	...actionControls,
	routes
} as ActionController<WorkflowRun> & { routes: WorkflowRunRoutes };
