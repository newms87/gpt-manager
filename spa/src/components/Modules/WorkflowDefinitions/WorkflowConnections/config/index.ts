import { WorkflowConnection } from "@/types";
import { DanxController } from "quasar-ui-danx";
import { actionControls } from "./actions";
import { controls } from "./controls";
import { routes } from "./routes";

export const dxWorkflowConnection = {
	...controls,
	...actionControls,
	routes
} as DanxController<WorkflowConnection>;
