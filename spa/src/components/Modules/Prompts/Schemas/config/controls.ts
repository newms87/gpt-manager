import { PromptSchemaRoutes } from "@/components/Modules/Prompts/Schemas/config/routes";
import { PromptSchema } from "@/types";
import { ActionController, PagedItems, useListControls } from "quasar-ui-danx";
import type { ShallowRef } from "vue";

export interface PromptSchemaPagedItems extends PagedItems {
	data: PromptSchema[];
}

export interface PromptSchemaControllerInterface extends ActionController {
	activeItem: ShallowRef<PromptSchema>;
	pagedItems: ShallowRef<PromptSchemaPagedItems>;
}

export const dxPromptSchema: PromptSchemaControllerInterface = useListControls("prompts.schemas", {
	label: "Prompt Schemas",
	routes: PromptSchemaRoutes
}) as PromptSchemaControllerInterface;
