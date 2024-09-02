import { ActionController, useListControls } from "quasar-ui-danx";
import { PromptDirectiveRoutes } from "./routes";

export const dxPromptDirective: ActionController = useListControls("prompts.directives", {
	label: "Prompt Directives",
	routes: PromptDirectiveRoutes
});
