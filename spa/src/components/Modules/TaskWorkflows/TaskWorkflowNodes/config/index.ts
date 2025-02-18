import { controls } from "@/components/Modules/TaskWorkflows/TaskWorkflowNodes/config/controls";
import { TaskWorkflowNode } from "@/types/task-workflows";
import { DanxController } from "quasar-ui-danx";
import { actionControls } from "./actions";
import { routes } from "./routes";

export const dxTaskWorkflowNode = {
	...controls,
	...actionControls,
	routes
} as DanxController<TaskWorkflowNode>;
