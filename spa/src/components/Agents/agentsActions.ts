import { AgentController } from "@/components/Agents/agentsControls";
import { Agents } from "@/routes/agents";
import { ConfirmDialog, useActions } from "quasar-ui-danx";
import { h } from "vue";

const onAction = Agents.applyAction;
const onBatchAction = Agents.batchAction;
const onFinish = result => {
	AgentController.setItemInList(result.item);
	AgentController.refreshAll();
};

function formatConfirmText(actionText, agents) {
	return Array.isArray(agents) ? `${actionText} ${agents?.length} agents` : `${actionText} ${agents.name}`;
}

const items = [
	{
		name: "update",
		debounce: 500,
		onFinish
	},
	{
		label: "Edit",
		name: "edit",
		menu: true,
		onAction: async (action, target) => AgentController.activatePanel(target, "edit")
	},
	{
		name: "delete",
		label: "Delete",
		class: "text-red-500",
		menu: true,
		batch: true,
		onFinish,
		vnode: ads => h(ConfirmDialog, { confirmText: formatConfirmText("Delete", ads) })
	}
];

export const { performAction, filterActions, actions } = useActions(items, {
	onAction,
	onBatchAction
});
