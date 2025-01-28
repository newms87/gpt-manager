import { PromptDirective } from "@/types";
import { ListController, useControls } from "quasar-ui-danx";
import { routes } from "./routes";

export const controls = useControls("prompts.directives", {
	label: "Prompt Directives",
	routes
}) as ListController<PromptDirective>;
