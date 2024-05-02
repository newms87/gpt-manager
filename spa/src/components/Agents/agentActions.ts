import { AgentController } from "@/components/Agents/agentControls";
import { AgentRoutes } from "@/routes/agentRoutes";
import { ActionOptions, ConfirmActionDialog, ConfirmDialog, TextField, useActions } from "quasar-ui-danx";
import { h, ref } from "vue";

const onAction = AgentRoutes.applyAction;
const onBatchAction = AgentRoutes.batchAction;
const onFinish = result => {
	AgentController.setItemInList(result.item);
	AgentController.refreshAll();
	AgentController.selectedRows.value = [];
};

const newAgentName = ref("");

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
			onFinish(result);
		}
	},
	{
		name: "update",
		debounce: 500,
		onFinish
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
		onFinish,
		vnode: target => h(ConfirmActionDialog, { action: "Delete", label: "Agents", target, confirmClass: "bg-red-900" })
	},
	{
		name: "create-thread",
		onFinish
	}
];

export const { performAction, filterActions, actions } = useActions(items, {
	onAction,
	onBatchAction
});
