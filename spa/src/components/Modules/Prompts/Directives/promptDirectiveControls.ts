import { PromptDirectiveRoutes } from "@/routes/promptRoutes";
import { ActionController, useListControls } from "quasar-ui-danx";

export const PromptDirectiveController: ActionController = useListControls("prompts.directives", {
	label: "Prompt Directives",
	routes: PromptDirectiveRoutes
});
