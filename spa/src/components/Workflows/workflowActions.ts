import { WorkflowController } from "@/components/Workflows/workflowControls";
import { WorkflowRoutes } from "@/routes/workflowRoutes";
import { ConfirmActionDialog, ConfirmDialog, TextField, useActions } from "quasar-ui-danx";
import { ActionOptions } from "quasar-ui-danx/types";
import { h, ref } from "vue";

const newWorkflowName = ref("");

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
		vnode: () => h(ConfirmDialog, { confirmText: "Create Workflow" }, {
			title: () => "Create Workflow",
			default: () => h(TextField, {
				modelValue: newWorkflowName.value,
				label: "Name",
				"onUpdate:model-value": value => newWorkflowName.value = value
			})
		}),
		onAction: (action, target) => WorkflowRoutes.applyAction(action, target, { name: newWorkflowName.value }),
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
		onFinish: WorkflowController.refreshAll
	}
];

export const { getAction, getActions } = useActions(items, forAllItems);
