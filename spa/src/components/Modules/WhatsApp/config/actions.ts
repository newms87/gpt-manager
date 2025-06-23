import { ActionOptions, useActions, withDefaultActions } from "quasar-ui-danx";
import { connectionControls } from "./controls";
import { whatsAppConnectionRoutes } from "./whatsapp-routes";

export const actions: ActionOptions[] = [
	...withDefaultActions("WhatsApp Connection", connectionControls)
];

export const actionControls = useActions(actions, { routes: whatsAppConnectionRoutes });
export const menuActions = actionControls.getActions(["edit", "delete"]);
export const batchActions = actionControls.getActions(["delete"]);