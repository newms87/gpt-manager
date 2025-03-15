import { ActionOptions, useActions, withDefaultActions } from "quasar-ui-danx";
import { controls } from "./controls";
import { routes } from "./routes";

export const actions: ActionOptions[] = [
	...withDefaultActions("Workflow Definition", controls),
	{
		name: "add-node"
	},
	{
		name: "remove-node"
	},
	{
		name: "add-connection"
	}
];

export const actionControls = useActions(actions, { routes });
export const menuActions = actionControls.getActions(["copy", "edit", "delete"]);
export const batchActions = actionControls.getActions(["delete"]);
