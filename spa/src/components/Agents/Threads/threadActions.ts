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
	}
];

export const { performAction, actions } = useActions(items, {
	onAction,
	onBatchAction
});
