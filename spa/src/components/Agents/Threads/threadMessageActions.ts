import { AgentController } from "@/components/Agents/agentControls";
import { MessageRoutes } from "@/routes/agentRoutes";
import { ConfirmActionDialog, useActions } from "quasar-ui-danx";
import { ActionOptions } from "quasar-ui-danx/types";
import { h } from "vue";

const forAllItems: ActionOptions = {
	onAction: MessageRoutes.applyAction,
	onBatchAction: MessageRoutes.batchAction
};
const items: ActionOptions[] = [
	{
		name: "update",
		debounce: 500,
		onFinish: AgentController.refreshAll
	},
	{
		name: "delete",
		label: "Delete",
		class: "text-red-500",
		menu: true,
		batch: true,
		onFinish: AgentController.refreshAll,
		vnode: target => h(ConfirmActionDialog, { action: "Delete", label: "Messages", target, confirmClass: "bg-red-900" })
	}
];

export const { performAction, actions } = useActions(items, forAllItems);
