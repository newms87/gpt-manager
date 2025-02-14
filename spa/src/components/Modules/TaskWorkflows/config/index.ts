import { TaskWorkflow } from "@/types/task-workflows";
import { DanxController } from "quasar-ui-danx";
import { actionControls, batchActions, menuActions } from "./actions";
import { controls } from "./controls";
import { filters } from "./filters";
import { routes } from "./routes";

export const dxTaskWorkflow = {
	...controls,
	...actionControls,
	menuActions,
	batchActions,
	filters,
	routes
} as DanxController<TaskWorkflow>;
