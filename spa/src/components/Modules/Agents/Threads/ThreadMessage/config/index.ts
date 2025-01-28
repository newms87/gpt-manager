import { AgentThreadMessage } from "@/types";
import { ActionController } from "quasar-ui-danx";
import { actionControls } from "./actions";
import { routes } from "./routes";

export const dxThreadMessage = {
	...actionControls,
	routes
} as ActionController<AgentThreadMessage>;
