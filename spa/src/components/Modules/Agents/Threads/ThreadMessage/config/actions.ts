import { controls as agentControls } from "@/components/Modules/Agents/config/controls";
import { ActionOptions, useActions, withDefaultActions } from "quasar-ui-danx";
import { routes } from "./routes";

export const actions: ActionOptions[] = [
	...withDefaultActions("Message")
];

export const actionControls = useActions(actions, { routes });
actionControls.modifyAction("delete", { onFinish: agentControls.getActiveItemDetails });
