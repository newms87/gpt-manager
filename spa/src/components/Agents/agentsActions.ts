import { AgentController } from "@/components/Agents/agentsControls";
import { Agents } from "@/routes/agents";
import { ConfirmDialog, TextField, useActions } from "quasar-ui-danx";
import { h, ref } from "vue";

const onAction = Agents.applyAction;
const onBatchAction = Agents.batchAction;
const onFinish = result => {
	AgentController.setItemInList(result.item);
	AgentController.refreshAll();
};

function nameOrCount(agents) {
	return Array.isArray(agents) ? `${agents?.length} agents` : `${agents.name}`;
}

const newAgentName = ref("");

const items = [
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
		onAction: (action, target) => Agents.applyAction(action, target, { name: newAgentName.value }),
		onFinish: (result) => {
			onFinish(result);
			AgentController.activatePanel(result.item, "edit");
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
		vnode: ads => h(ConfirmDialog, { confirmText: `Delete ${nameOrCount(ads)}`, confirmClass: "bg-red-900" }, {
			title: () => "Delete Agent",
			default: () => `Are you sure you want to delete ${nameOrCount(ads)}?`
		})
	}
];

export const { performAction, filterActions, actions } = useActions(items, {
	onAction,
	onBatchAction
});
