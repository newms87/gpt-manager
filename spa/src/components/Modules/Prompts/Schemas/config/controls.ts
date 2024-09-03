import { PromptSchema } from "@/types";
import { ListController, PagedItems, useListControls } from "quasar-ui-danx";
import type { ShallowRef } from "vue";
import { routes } from "./routes";

export interface PromptSchemaPagedItems extends PagedItems {
	data: PromptSchema[];
}

export interface PromptSchemaControllerInterface extends ListController {
	activeItem: ShallowRef<PromptSchema>;
	pagedItems: ShallowRef<PromptSchemaPagedItems>;
}

export const controls: PromptSchemaControllerInterface = useListControls("prompts.schemas", {
	label: "Prompt Schemas",
	routes
}) as PromptSchemaControllerInterface;
