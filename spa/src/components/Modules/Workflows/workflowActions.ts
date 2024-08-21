import { ImportWorkflowDialog } from "@/components/Modules/Workflows/Shared";
import { WorkflowController } from "@/components/Modules/Workflows/workflowControls";
import { CreateNewWithNameDialog } from "@/components/Shared";
import { WorkflowAssignmentRoutes, WorkflowJobRoutes, WorkflowRoutes } from "@/routes/workflowRoutes";
import { Workflow } from "@/types";
import {
	FaSolidCopy as CopyIcon,
	FaSolidDatabase as ExportJsonIcon,
	FaSolidPencil as EditIcon,
	FaSolidTrash as DeleteIcon
} from "danx-icon";
import { ActionOptions, ConfirmActionDialog, useActions } from "quasar-ui-danx";
import { h } from "vue";

// This is the default action options for all items
const forAllItems: Partial<ActionOptions> = {
	onAction: WorkflowRoutes.applyAction,
	onBatchAction: WorkflowRoutes.batchAction,
	onBatchSuccess: WorkflowController.clearSelectedRows
};

const items: ActionOptions[] = [
	{
		name: "create",
		label: "Create Workflow",
		vnode: () => h(CreateNewWithNameDialog, { title: "Create Workflow" }),
		onFinish: (result) => {
			WorkflowController.activatePanel(result.item, "edit");
			WorkflowController.loadListAndSummary();
		}
	},
	{
		name: "update"
	},
	{
		name: "copy",
		label: "Copy",
		icon: CopyIcon,
		menu: true,
		onSuccess: WorkflowController.loadListAndSummary
	},
	{
		name: "update-debounced",
		alias: "update",
		debounce: 1000
	},
	{
		label: "Edit",
		name: "edit",
		icon: EditIcon,
		menu: true,
		onAction: (action, target) => WorkflowController.activatePanel(target, "edit")
	},
	{
		name: "delete",
		label: "Delete",
		class: "text-red-500",
		iconClass: "text-red-500",
		icon: DeleteIcon,
		menu: true,
		batch: true,
		onFinish: WorkflowController.loadListAndSummary,
		vnode: target => h(ConfirmActionDialog, {
			action: "Delete",
			label: "Workflows",
			target,
			confirmClass: "bg-red-900"
		})
	},
	{
		name: "run-workflow"
	},
	{
		name: "export-json",
		label: "Export as JSON",
		icon: ExportJsonIcon,
		menu: true,
		onAction: async (action, target: Workflow) => await WorkflowRoutes.exportAsJson(target)
	},
	{
		name: "import-json",
		label: "Import from JSON",
		onAction: WorkflowRoutes.applyAction,
		onFinish: WorkflowController.loadListAndSummary,
		vnode: () => h(ImportWorkflowDialog)
	},
	{
		name: "create-job",
		vnode: () => h(CreateNewWithNameDialog, { title: "Create Workflow Job", confirmText: "Create Job" })
	},
	{
		name: "update-job",
		alias: "update",
		optimistic: true,
		onAction: WorkflowJobRoutes.applyAction
	},
	{
		name: "update-job-debounced",
		alias: "update",
		debounce: 500,
		onAction: WorkflowJobRoutes.applyAction
	},
	{
		name: "delete-job",
		alias: "delete",
		vnode: (target) => h(ConfirmActionDialog, { action: "Delete Job", target, confirmClass: "bg-red-700" }),
		onAction: async (...params) => {
			await WorkflowJobRoutes.applyAction(...params);
			await WorkflowController.getActiveItemDetails();
		}
	},
	{
		name: "set-dependencies",
		onAction: WorkflowJobRoutes.applyAction,
		onFinish: WorkflowController.getActiveItemDetails
	},
	{
		name: "assign-agent",
		onAction: WorkflowJobRoutes.applyAction
	},
	{
		name: "unassign-agent",
		alias: "delete",
		onAction: WorkflowAssignmentRoutes.applyAction,
		onFinish: WorkflowController.getActiveItemDetails
	}
];

export const { getAction, getActions } = useActions(items, forAllItems);
