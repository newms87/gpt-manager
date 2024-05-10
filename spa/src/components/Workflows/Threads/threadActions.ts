import { AgentController } from "@/components/Agents/agentControls";
import { ThreadRoutes } from "@/routes/agentRoutes";
import { ConfirmActionDialog, useActions } from "quasar-ui-danx";
import { ActionOptions } from "quasar-ui-danx/types";
import { h } from "vue";

const forAllItems: ActionOptions = {
	onAction: ThreadRoutes.applyAction,
	onBatchAction: ThreadRoutes.batchAction
};

const items: ActionOptions[] = [
	{
		name: "update",
		debounce: 500
	},
	{
		name: "delete",
		label: "Delete",
		class: "text-red-500",
		menu: true,
		batch: true,
		onFinish: AgentController.refreshAll,
		vnode: target => h(ConfirmActionDialog, { action: "Delete", label: "Threads", target, confirmClass: "bg-red-900" })
	},
	{
		name: "create-message",
		optimistic: (action, target, data) => {
			target.messages.push({
				...data,
				id: "new",
				title: "",
				role: "user"
			});
		},
		onFinish: AgentController.refreshAll
	}
];

export const { getAction, getActions } = useActions(items, forAllItems);
