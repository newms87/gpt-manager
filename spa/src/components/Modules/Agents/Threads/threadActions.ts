import { AgentController } from "@/components/Modules/Agents/agentControls";
import { ThreadRoutes } from "@/routes/agentRoutes";
import { ThreadMessage } from "@/types";
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
	},
	{
		name: "reset-to-message",
		label: "Reset To Message",
		class: "text-red-500",
		onFinish: AgentController.getActiveItemDetails,
		vnode: (target: ThreadMessage) => h(ConfirmActionDialog, {
			action: "Reset To Message",
			label: "Delete all following messages",
			content: "Are you sure you want to delete all messages following this one?",
			target,
			confirmClass: "bg-red-900"
		})
	}
];

export const { getAction, getActions } = useActions(items, forAllItems);
