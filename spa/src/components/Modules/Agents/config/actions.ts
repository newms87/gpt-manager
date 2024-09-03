import { AgentsBatchUpdateDialog } from "@/components/Modules/Agents/Dialogs";
import { WorkflowAssignmentRoutes } from "@/routes/workflowRoutes";
import { FaSolidCopy as CopyIcon, FaSolidPencil as EditIcon } from "danx-icon";
import { ActionOptions, useActions, withDefaultActions } from "quasar-ui-danx";
import { h } from "vue";
import { controls } from "./controls";
import { routes } from "./routes";

export const actions: ActionOptions[] = [
	...withDefaultActions(controls),
	{
		name: "copy",
		label: "Copy",
		icon: CopyIcon,
		menu: true,
		onSuccess: controls.loadListAndSummary
	},
	{
		name: "batch-update",
		alias: "update",
		label: "Batch Update",
		batch: true,
		icon: EditIcon,
		onFinish: controls.loadListAndSummary,
		onBatchSuccess: controls.clearSelectedRows,
		vnode: ads => h(AgentsBatchUpdateDialog, { confirmText: "Apply to " + ads.length + " Agents" })
	},
	{
		name: "create-thread"
	},
	{
		name: "unassign-agent",
		alias: "delete",
		onAction: WorkflowAssignmentRoutes.applyAction,
		onFinish: controls.getActiveItemDetails
	},
	{
		name: "update-directives",
		optimistic: true
	}
];

export const actionControls = useActions(actions, { routes });
