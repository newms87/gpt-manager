import { controls } from "@/components/Modules/Workflows/WorkflowInputs/config/controls";
import { routes } from "@/components/Modules/Workflows/WorkflowInputs/config/routes";
import { ActionOptions, useActions, withDefaultActions } from "quasar-ui-danx";

const actions: ActionOptions[] = [
	...withDefaultActions("Workflow Input", controls)
];

export const actionControls = useActions(actions, { routes, controls });
export const menuActions = actionControls.getActions(["copy", "edit", "delete"]);
export const batchActions = actionControls.getActions(["delete"]);
