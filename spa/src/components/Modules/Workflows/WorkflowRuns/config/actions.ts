import { ActionOptions, ConfirmActionDialog, useActions } from "quasar-ui-danx";
import { h } from "vue";
import { routes } from "./routes";

const actions: ActionOptions[] = [
	{
		name: "delete",
		label: "Delete",
		class: "text-red-500",
		vnode: target => h(ConfirmActionDialog, {
			action: "Delete Workflow Run",
			target,
			confirmClass: "bg-red-900"
		})
	},
	{
		name: "restart-workflow",
		vnode: target => h(ConfirmActionDialog, {
			action: "Restart Workflow Run",
			target
		})
	},
	{
		name: "restart-job",
		vnode: target => h(ConfirmActionDialog, {
			action: "Restart Workflow Job Run",
			target
		})
	}
];

export const actionControls = useActions(actions, { routes });
