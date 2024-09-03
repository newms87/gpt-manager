import { ActionController } from "quasar-ui-danx";
import { actionControls, menuActions } from "./actions";
import { routes } from "./routes";

export const dxAgentThread = {
	...actionControls,
	menuActions,
	routes
} as ActionController;
