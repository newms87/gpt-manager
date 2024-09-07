import { Workflow } from "@/types";
import { ListController, PagedItems, useControls } from "quasar-ui-danx";
import type { ShallowRef } from "vue";
import { routes } from "./routes";

export interface WorkflowPagedItems extends PagedItems {
	data: Workflow[];
}

export interface WorkflowControllerInterface extends ListController {
	activeItem: ShallowRef<Workflow>;
	pagedItems: ShallowRef<WorkflowPagedItems>;
}

export const controls = useControls("workflows", {
	label: "Workflows",
	routes
}) as WorkflowControllerInterface;
