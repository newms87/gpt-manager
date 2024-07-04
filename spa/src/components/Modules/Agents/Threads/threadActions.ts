import { AgentController } from "@/components/Modules/Agents/agentControls";
import { ThreadRoutes } from "@/routes/agentRoutes";
import { ThreadMessage } from "@/types";
import { FaSolidCopy as CopyIcon, FaSolidTrash as DeleteIcon } from "danx-icon";
import {
	ActionOptions,
	ActionOptionsPartial,
	ConfirmActionDialog,
	pollUntil,
	storeObject,
	useActions
} from "quasar-ui-danx";
import { h } from "vue";

const forAllItems: ActionOptionsPartial = {
	onAction: ThreadRoutes.applyAction,
	onBatchAction: ThreadRoutes.batchAction
};

const items: ActionOptions[] = [
	{
		name: "update",
		debounce: 500
	},
	{
		name: "run",
		onAction: async (action, target) => {
			const response = await ThreadRoutes.applyAction(action, target);

			if (response.success) {
				pollUntil(async () => {
					const thread = await ThreadRoutes.details(target);
					storeObject(thread);
					return !thread.is_running;
				}, 1000);
			}

			return response;
		}
	},
	{
		name: "copy",
		label: "Copy",
		icon: CopyIcon,
		menu: true,
		onSuccess: AgentController.getActiveItemDetails
	},
	{
		name: "delete",
		label: "Delete",
		iconClass: "text-red-500",
		icon: DeleteIcon,
		menu: true,
		batch: true,
		onFinish: AgentController.getActiveItemDetails,
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
		onFinish: AgentController.getActiveItemDetails
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
