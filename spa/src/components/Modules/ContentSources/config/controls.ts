import { ContentSource } from "@/types";
import { ListController, PagedItems, useControls } from "quasar-ui-danx";
import type { ShallowRef } from "vue";
import { routes } from "./routes";

export interface ContentSourcePagedItems extends PagedItems {
	data: ContentSource[];
}

export interface ContentSourceControllerInterface extends ListController {
	activeItem: ShallowRef<ContentSource>;
	pagedItems: ShallowRef<ContentSourcePagedItems>;
}


export const controls = useControls("content-sources", {
	label: "Content Sources",
	routes
}) as ContentSourceControllerInterface;
