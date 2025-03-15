import { WorkflowDefinition } from "@/types";
import { DanxController } from "quasar-ui-danx";
import { actionControls, batchActions, menuActions } from "./actions";
import { controls } from "./controls";
import { filters } from "./filters";
import { routes } from "./routes";

export const dxWorkflowDefinition = {
	...controls,
	...actionControls,
	menuActions,
	batchActions,
	filters,
	routes
} as DanxController<WorkflowDefinition>;
