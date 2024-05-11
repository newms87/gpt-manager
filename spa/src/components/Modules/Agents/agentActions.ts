import { AgentController } from "@/components/Modules/Agents/agentControls";
import { CreateNewWithNameDialog } from "@/components/Shared";
import { AgentRoutes } from "@/routes/agentRoutes";
import { ConfirmActionDialog, useActions } from "quasar-ui-danx";
import { ActionOptions } from "quasar-ui-danx/types";
import { h } from "vue";

// This is the default action options for all items
const forAllItems: ActionOptions = {
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
			AgentController.refreshAll();
		}
	},
	{
		name: "update",
		onFinish: AgentController.loadList
	},
	{
		name: "update-debounced",
		alias: "update",
		debounce: 1000,
		onFinish: AgentController.loadList
	},
	{
		label: "Edit",
		name: "edit",
		menu: true,
		onAction: (action, target) => AgentController.activatePanel(target, "edit")
	},
	{
		name: "delete",
		label: "Delete",
		class: "text-red-500",
		menu: true,
		batch: true,
		onFinish: AgentController.refreshAll,
		vnode: target => h(ConfirmActionDialog, { action: "Delete", label: "Agents", target, confirmClass: "bg-red-900" })
	},
	{
		name: "create-thread",
		onFinish: AgentController.refreshAll
	}
];

export const { getAction, getActions } = useActions(items, forAllItems);
