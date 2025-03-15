import { WorkflowInput } from "@/types";
import { DanxController } from "quasar-ui-danx";
import { actionControls, batchActions, menuActions } from "./actions";
import { columns } from "./columns";
import { controls } from "./controls";
import { filters } from "./filters";
import { panels } from "./panels";
import { routes } from "./routes";

export const dxWorkflowInput = {
	...controls,
	...actionControls,
	menuActions,
	batchActions,
	columns,
	filters,
	panels,
	routes
} as DanxController<WorkflowInput>;
