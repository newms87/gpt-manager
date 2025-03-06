import { ActionOptions, useActions, withDefaultActions } from "quasar-ui-danx";
import { controls } from "./controls";
import { routes } from "./routes";

const actions: ActionOptions[] = [
	...withDefaultActions("Workflow Input", controls)
];

export const actionControls = useActions(actions, { routes, controls });
export const menuActions = actionControls.getActions(["copy", "edit", "delete"]);
export const batchActions = actionControls.getActions(["delete"]);
