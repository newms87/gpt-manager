import { ActionOptions, useActions, withDefaultActions } from "quasar-ui-danx";
import { controls } from "./controls";
import { routes } from "./routes";

const items: ActionOptions[] = [
	...withDefaultActions("Content Source", controls),
	{
		name: "fetch"
	}
];

export const actionControls = useActions(items, { routes, controls });
export const menuActions = actionControls.getActions(["copy", "edit", "delete"]);
export const batchActions = actionControls.getActions(["delete"]);
