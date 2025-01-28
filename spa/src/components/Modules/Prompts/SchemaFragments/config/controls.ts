import { PromptSchemaFragment } from "@/types";
import { ListController, useControls } from "quasar-ui-danx";
import { routes } from "./routes";

export const controls = useControls("prompts.schemas", {
	label: "Prompt Schema Fragments",
	routes
}) as ListController<PromptSchemaFragment>;
