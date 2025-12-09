import { ActionOptions, useActions, withDefaultActions } from "quasar-ui-danx";
import { controls } from "./controls";
import { routes } from "./routes";

export const actions: ActionOptions[] = [
	...withDefaultActions("Task Definition", controls),
	{
		name: "clear-meta",
		label: "Clear Metadata",
		vnode: () => null,
		onAction: async (action, target) => {
			return await routes.applyAction(action, target);
		},
		onFinish: (result, { target }) => {
			if (target) {
				target.meta = null;
			}
		}
	}
];
export const actionControls = useActions(actions, { routes });
export const menuActions = actionControls.getActions(["copy", "edit", "delete"]);
export const batchActions = actionControls.getActions(["delete"]);
