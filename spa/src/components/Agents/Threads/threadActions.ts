import { AgentController } from "@/components/Agents/agentControls";
import { ThreadRoutes } from "@/routes/agentRoutes";
import { ActionOptions, ConfirmActionDialog, useActions } from "quasar-ui-danx";
import { h } from "vue";

const onAction = ThreadRoutes.applyAction;
const onBatchAction = ThreadRoutes.batchAction;
const onFinish = AgentController.refreshAll;

const items: ActionOptions[] = [
	{
		name: "update",
		debounce: 500,
		onFinish
	},
	{
		name: "delete",
		label: "Delete",
		class: "text-red-500",
		menu: true,
		batch: true,
		onFinish,
		vnode: target => h(ConfirmActionDialog, { action: "Delete", label: "Threads", target, confirmClass: "bg-red-900" })
	},
	{
		name: "create-message",
		optimistic: (action, target, data) => {
			target.messages.push({
				...data,
				id: "new",
				title: "(Empty)",
				role: "user"
			});
		},
		onSuccess: (results, target) => {
			target.messages = results.item.messages;
		},
		onFinish
	}
];

export const { performAction, actions } = useActions(items, {
	onAction,
	onBatchAction
});
