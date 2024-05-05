import { AgentController } from "@/components/Agents/agentControls";
import { AgentRoutes } from "@/routes/agentRoutes";
import { ConfirmActionDialog, ConfirmDialog, TextField, useActions } from "quasar-ui-danx";
import { ActionOptions } from "quasar-ui-danx/types";
import { h, ref } from "vue";

const newAgentName = ref("");

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
		vnode: () => h(ConfirmDialog, { confirmText: "Create Agent" }, {
			title: () => "Create Agent",
			default: () => h(TextField, {
				modelValue: newAgentName.value,
				label: "Name",
				"onUpdate:model-value": value => newAgentName.value = value
			})
		}),
		onAction: (action, target) => AgentRoutes.applyAction(action, target, { name: newAgentName.value }),
		onFinish: (result) => {
			AgentController.activatePanel(result.item, "edit");
			AgentController.refreshAll();
		}
	},
	{
		name: "update",
		debounce: 1000,
		onFinish: AgentController.refreshAll
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
