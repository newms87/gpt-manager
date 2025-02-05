import { TaskProcess } from "@/types";
import { DanxController } from "quasar-ui-danx";
import { actionControls } from "./actions";
import { controls } from "./controls";
import { routes } from "./routes";

export const dxTaskProcess = {
	...controls,
	...actionControls,
	routes
} as DanxController<TaskProcess>;
