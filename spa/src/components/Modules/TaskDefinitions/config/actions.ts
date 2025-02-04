import { ActionOptions, useActions, withDefaultActions } from "quasar-ui-danx";
import { controls } from "./controls";
import { routes } from "./routes";

export const actions: ActionOptions[] = [
	...withDefaultActions("Task Definition", controls),
	{
		name: "add-agent"
	},
	{
		name: "update-agent"
	},
	{
		name: "remove-agent"
	},
	{
		name: "copy-agent"
	}
];

export const actionControls = useActions(actions, { routes });
export const menuActions = actionControls.getActions(["copy", "edit", "delete"]);
export const batchActions = actionControls.getActions(["delete"]);
