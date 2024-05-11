import { WorkflowController } from "@/components/Workflows/workflowControls";
import { WorkflowRoutes } from "@/routes/workflowRoutes";
import { ConfirmActionDialog, useActions } from "quasar-ui-danx";
import { ActionOptions } from "quasar-ui-danx/types";
import { h } from "vue";
import { CreateNewWithNameDialog } from "../Shared";

// This is the default action options for all items
const forAllItems: ActionOptions = {
	onAction: WorkflowRoutes.applyAction,
	onBatchAction: WorkflowRoutes.batchAction,
	onBatchSuccess: WorkflowController.clearSelectedRows
};

const items: ActionOptions[] = [
	{
		name: "create",
		label: "Create Workflow",
		vnode: () => h(CreateNewWithNameDialog, { title: "Create Workflow" }),
		onAction: (action, target, input) => WorkflowRoutes.applyAction(action, target, input),
		onFinish: (result) => {
			WorkflowController.activatePanel(result.item, "edit");
			WorkflowController.refreshAll();
		}
	},
	{
		name: "update",
		onFinish: WorkflowController.loadList
	},
	{
		name: "update-debounced",
		alias: "update",
		debounce: 1000,
		onFinish: WorkflowController.loadList
	},
	{
		label: "Edit",
		name: "edit",
		menu: true,
		onAction: (action, target) => WorkflowController.activatePanel(target, "edit")
	},
	{
		name: "delete",
		label: "Delete",
		class: "text-red-500",
		menu: true,
		batch: true,
		onFinish: WorkflowController.refreshAll,
		vnode: target => h(ConfirmActionDialog, {
			action: "Delete",
			label: "Workflows",
			target,
			confirmClass: "bg-red-900"
		})
	},
	{
		name: "create-job",
		vnode: () => h(CreateNewWithNameDialog, { title: "Create Workflow Job", confirmText: "Create Job" }),
		onAction: (action, target, input) => WorkflowRoutes.applyAction(action, target, input),
		onFinish: WorkflowController.refreshAll
	}
];

export const { getAction, getActions } = useActions(items, forAllItems);
