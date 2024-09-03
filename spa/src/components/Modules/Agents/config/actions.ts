import { dxAgent } from "@/components/Modules/Agents/config/controls";
import { AgentsBatchUpdateDialog } from "@/components/Modules/Agents/Dialogs";
import { WorkflowAssignmentRoutes } from "@/routes/workflowRoutes";
import { FaSolidCopy as CopyIcon, FaSolidPencil as EditIcon } from "danx-icon";
import { ActionOptions, useActions, withDefaultActions } from "quasar-ui-danx";
import { h } from "vue";
import { AgentRoutes } from "./routes";


// This is the default action options for all items
const forAllItems: Partial<ActionOptions> = {
	onAction: AgentRoutes.applyAction,
	onBatchAction: AgentRoutes.batchAction,
	onBatchSuccess: dxAgent.clearSelectedRows
};

const items: ActionOptions[] = [
	...withDefaultActions(dxAgent),
	{
		name: "copy",
		label: "Copy",
		icon: CopyIcon,
		menu: true,
		onSuccess: dxAgent.loadListAndSummary
	},
	{
		name: "batch-update",
		alias: "update",
		label: "Batch Update",
		batch: true,
		icon: EditIcon,
		onFinish: dxAgent.loadListAndSummary,
		vnode: ads => h(AgentsBatchUpdateDialog, { confirmText: "Apply to " + ads.length + " Agents" })
	},
	{
		name: "create-thread"
	},
	{
		name: "unassign-agent",
		alias: "delete",
		onAction: WorkflowAssignmentRoutes.applyAction,
		onFinish: dxAgent.getActiveItemDetails
	},
	{
		name: "update-directives",
		optimistic: true
	}
];

export const { getAction, getActions, extendAction } = useActions(items, forAllItems);
