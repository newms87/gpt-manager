import { TaskWorkflowRun } from "@/types/task-workflows";
import { DanxController } from "quasar-ui-danx";
import { actionControls } from "./actions";
import { controls } from "./controls";
import { routes } from "./routes";

export const dxTaskWorkflowRun = {
	...controls,
	...actionControls,
	routes
} as DanxController<TaskWorkflowRun>;
