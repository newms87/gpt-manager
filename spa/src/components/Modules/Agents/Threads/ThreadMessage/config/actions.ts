import { controls as agentControls } from "@/components/Modules/Agents/config/controls";
import { ThreadMessage } from "@/types/agents";
import { ActionOptions, ConfirmActionDialog, useActions } from "quasar-ui-danx";
import { h } from "vue";
import { routes } from "./routes";

export const actions: ActionOptions[] = [
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
		onFinish: agentControls.getActiveItemDetails,
		// If the thread is empty, allow deleting w/o confirmation
		vnode: (target: ThreadMessage) => !target.content ? false : h(ConfirmActionDialog, {
			action: "Delete",
			label: "Messages",
			target,
			confirmClass: "bg-red-900"
		})
	}
];

export const actionControls = useActions(actions, { routes });
