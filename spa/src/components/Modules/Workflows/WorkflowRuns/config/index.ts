import { WorkflowRun, WorkflowRunRoutes } from "@/types";
import { ActionController } from "quasar-ui-danx";
import { actionControls } from "./actions";
import { routes } from "./routes";

interface WorkflowRunController extends ActionController<WorkflowRun> {
	routes: WorkflowRunRoutes;
}

export const dxWorkflowRun = {
	...actionControls,
	routes
} as WorkflowRunController;
