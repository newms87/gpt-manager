import { TaskRun, TaskRunRoutes } from "@/types";
import { DanxController } from "quasar-ui-danx";
import { actionControls } from "./actions";
import { controls } from "./controls";
import { routes } from "./routes";

export const dxTaskRun = {
	...controls,
	...actionControls,
	routes
} as DanxController<TaskRun> & { routes: TaskRunRoutes };
