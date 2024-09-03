import { controls as agentControls } from "@/components/Modules/Agents/config/controls";
import { ThreadMessage } from "@/types";
import {
	ActionOptions,
	ConfirmActionDialog,
	pollUntil,
	storeObject,
	useActions,
	withDefaultActions
} from "quasar-ui-danx";
import { h } from "vue";
import { routes } from "./routes";

export const actions: ActionOptions[] = [
	...withDefaultActions("Agent Thread"),
	{
		name: "run",
		onAction: async (action, target) => {
			const response = await routes.applyAction(action, target);

			if (response.success) {
				pollUntil(async () => {
					const thread = await routes.details(target);
					storeObject(thread);
					return !thread.is_running;
				}, 1000);
			}

			return response;
		}
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
		onSuccess: agentControls.getActiveItemDetails
	},
	{
		name: "reset-to-message",
		label: "Reset To Message",
		class: "text-red-500",
		onSuccess: agentControls.getActiveItemDetails,
		vnode: (target: ThreadMessage) => h(ConfirmActionDialog, {
			action: "Reset To Message",
			label: "Delete all following messages",
			content: "Are you sure you want to delete all messages following this one?",
			target,
			confirmClass: "bg-red-900"
		})
	}
];

export const actionControls = useActions(actions, { routes });

actionControls.modifyAction("delete", { onSuccess: agentControls.getActiveItemDetails });
actionControls.modifyAction("copy", { onSuccess: agentControls.getActiveItemDetails });

export const menuActions = actionControls.getActions(["copy", "delete"]);
