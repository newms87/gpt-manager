import { WorkflowRun, WorkflowRunRoutes } from "@/types";
import { DanxController } from "quasar-ui-danx";
import { actionControls } from "./actions";
import { controls } from "./controls";
import { routes } from "./routes";

export const dxWorkflowRun = {
	...controls,
	...actionControls,
	routes
} as DanxController<WorkflowRun> & { routes: WorkflowRunRoutes };
