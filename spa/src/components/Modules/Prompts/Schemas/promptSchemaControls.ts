import { PromptSchemaRoutes } from "@/routes/promptRoutes";
import { ActionController, useListControls } from "quasar-ui-danx";

export const PromptSchemaController: ActionController = useListControls("prompts.schemas", {
	label: "Prompt Schemas",
	routes: PromptSchemaRoutes
});
