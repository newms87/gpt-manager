import { TaskWorkflowConnection } from "@/types/task-workflows";
import { DanxController } from "quasar-ui-danx";
import { actionControls } from "./actions";
import { controls } from "./controls";
import { routes } from "./routes";

export const dxTaskWorkflowConnection = {
	...controls,
	...actionControls,
	routes
} as DanxController<TaskWorkflowConnection>;
