import { TaskDefinition } from "@/types";
import { TaskWorkflow } from "@/types/task-workflows";
import { ActionOptions, useActions, withDefaultActions } from "quasar-ui-danx";
import { h } from "vue";
import SelectTaskNodeDialog from "../SelectTaskNodeDialog.vue";
import { controls } from "./controls";
import { routes } from "./routes";

export const actions: ActionOptions[] = [
	...withDefaultActions("Task Workflow", controls),
	{
		name: "add-node",
		vnode: (taskWorkflow: TaskWorkflow) => h(SelectTaskNodeDialog, { taskWorkflow }),
		onAction: async (action, target, input: TaskDefinition) => {
			return routes.applyAction("add-node", target, { task_definition_id: input.id });
		}
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
