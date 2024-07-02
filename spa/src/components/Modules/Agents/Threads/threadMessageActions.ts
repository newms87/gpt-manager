import { AgentController } from "@/components/Modules/Agents/agentControls";
import { MessageRoutes } from "@/routes/agentRoutes";
import { ThreadMessage } from "@/types/agents";
import { ConfirmActionDialog, useActions } from "quasar-ui-danx";
import { ActionOptions } from "quasar-ui-danx";
import { h } from "vue";

const forAllItems: ActionOptions = {
	onAction: MessageRoutes.applyAction,
	onBatchAction: MessageRoutes.batchAction
};
const items: ActionOptions[] = [
	{
		name: "update",
		optimistic: true
	},
	{
		name: "updateDebounced",
		alias: "update",
		debounce: 500,
		optimistic: true
	},
	{
		name: "delete",
		label: "Delete",
		class: "text-red-500",
		menu: true,
		batch: true,
		onFinish: AgentController.getActiveItemDetails,
		vnode: (target: ThreadMessage) => !target.content ? false : h(ConfirmActionDialog, {
			action: "Delete",
			label: "Messages",
			target,
			confirmClass: "bg-red-900"
		})
	}
];

export const { getAction, getActions } = useActions(items, forAllItems);
