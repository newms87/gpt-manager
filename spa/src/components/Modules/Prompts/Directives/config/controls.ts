import { PromptDirective } from "@/types";
import { ListController, PagedItems, useListControls } from "quasar-ui-danx";
import type { ShallowRef } from "vue";
import { routes } from "./routes";

export interface PromptDirectivePagedItems extends PagedItems {
	data: PromptDirective[];
}

export interface PromptDirectiveControllerInterface extends ListController {
	activeItem: ShallowRef<PromptDirective>;
	pagedItems: ShallowRef<PromptDirectivePagedItems>;
}

export const controls = useListControls("prompts.directives", {
	label: "Prompt Directives",
	routes
}) as PromptDirectiveControllerInterface;
