import { controls } from "@/components/Modules/WorkflowDefinitions/WorkflowNodes/config/controls";
import { WorkflowNode } from "@/types";
import { DanxController } from "quasar-ui-danx";
import { actionControls } from "./actions";
import { routes } from "./routes";

export const dxWorkflowNode = {
	...controls,
	...actionControls,
	routes
} as DanxController<WorkflowNode>;
