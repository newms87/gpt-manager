import { AgentController } from "@/components/Modules/Agents/agentControls";
import { AgentsBatchUpdateDialog } from "@/components/Modules/Agents/Dialogs";
import { CreateNewWithNameDialog } from "@/components/Shared";
import { AgentRoutes } from "@/routes/agentRoutes";
import { WorkflowAssignmentRoutes } from "@/routes/workflowRoutes";
import { FaSolidCopy as CopyIcon, FaSolidPencil as EditIcon, FaSolidTrash as DeleteIcon } from "danx-icon";
import { ActionOptions, ActionTarget, ConfirmActionDialog, useActions } from "quasar-ui-danx";
import { h } from "vue";


// This is the default action options for all items
const forAllItems: Partial<ActionOptions> = {
	onAction: AgentRoutes.applyAction,
	onBatchAction: AgentRoutes.batchAction,
	onBatchSuccess: AgentController.clearSelectedRows
};

const items: ActionOptions[] = [
	{
		name: "create",
		label: "Create Agent",
		vnode: () => h(CreateNewWithNameDialog, { title: "Create Agent" }),
		onAction: (action, target, input) => AgentRoutes.applyAction(action, target, input),
		onFinish: (result) => {
			AgentController.activatePanel(result.item, "edit");
			AgentController.loadListAndSummary();
		}
	},
	{
		name: "update",
		optimistic: true
	},
	{
		name: "update-debounced",
		alias: "update",
		debounce: 1000
	},
	{
		name: "copy",
		label: "Copy",
		icon: CopyIcon,
		menu: true,
		onSuccess: AgentController.loadListAndSummary
	},
	{
		label: "Edit",
		name: "edit",
		icon: EditIcon,
		menu: true,
		onAction: (action, target) => AgentController.activatePanel(target, "edit")
	},
	{
		name: "batch-update",
		alias: "update",
		label: "Batch Update",
		batch: true,
		icon: EditIcon,
		onFinish: AgentController.loadListAndSummary,
		vnode: ads => h(AgentsBatchUpdateDialog, { confirmText: "Apply to " + ads.length + " Agents" })
	},
	{
		name: "delete",
		label: "Delete",
		class: "text-red-500",
		iconClass: "text-red-500",
		icon: DeleteIcon,
		menu: true,
		batch: true,
		onFinish: AgentController.loadListAndSummary,
		vnode: (target: ActionTarget) => h(ConfirmActionDialog, {
			action: "Delete",
			label: "Agents",
			target,
			confirmClass: "bg-red-900"
		})
	},
	{
		name: "create-thread"
	},
	{
		name: "unassign-agent",
		alias: "delete",
		onAction: WorkflowAssignmentRoutes.applyAction,
		onFinish: AgentController.getActiveItemDetails
	},
	{
		name: "update-directives",
		optimistic: true
	}
];

export const { getAction, getActions, extendAction } = useActions(items, forAllItems);
