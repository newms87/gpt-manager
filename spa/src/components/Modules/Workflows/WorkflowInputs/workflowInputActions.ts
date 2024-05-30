import { WorkflowInputController } from "@/components/Modules/Workflows/WorkflowInputs/workflowInputControls";
import { CreateNewWithNameDialog } from "@/components/Shared";
import { WorkflowInputRoutes } from "@/routes/workflowInputRoutes";
import { ConfirmActionDialog, useActions } from "quasar-ui-danx";
import { ActionOptions } from "quasar-ui-danx/types";
import { h } from "vue";

// This is the default action options for all items
const forAllItems: ActionOptions = {
	onAction: WorkflowInputRoutes.applyAction,
	onBatchAction: WorkflowInputRoutes.batchAction,
	onBatchSuccess: WorkflowInputController.clearSelectedRows
};

const items: ActionOptions[] = [
	{
		name: "create",
		label: "Create WorkflowInput",
		vnode: () => h(CreateNewWithNameDialog, { title: "Create WorkflowInput" }),
		onFinish: (result) => {
			WorkflowInputController.activatePanel(result.item, "edit");
			WorkflowInputController.loadListAndSummary();
		}
	},
	{
		name: "update"
	},
	{
		name: "update-debounced",
		alias: "update",
		debounce: 1000
	},
	{
		label: "Edit",
		name: "edit",
		menu: true,
		onAction: (action, target) => WorkflowInputController.activatePanel(target, "edit")
	},
	{
		name: "delete",
		label: "Delete",
		class: "text-red-500",
		menu: true,
		batch: true,
		onFinish: WorkflowInputController.loadListAndSummary,
		vnode: target => h(ConfirmActionDialog, {
			action: "Delete",
			label: "WorkflowInputs",
			target,
			confirmClass: "bg-red-900"
		})
	}
];

export const { getAction, getActions } = useActions(items, forAllItems);
