import { Agent } from "@/types";
import { ListController, PagedItems, useControls } from "quasar-ui-danx";
import type { ShallowRef } from "vue";
import { routes } from "./routes";

export interface AgentPagedItems extends PagedItems {
	data: Agent[];
}

export interface AgentControllerInterface extends ListController {
	activeItem: ShallowRef<Agent>;
	pagedItems: ShallowRef<AgentPagedItems>;
}

export const controls = useControls("agents", {
	label: "Agents",
	routes
}) as AgentControllerInterface;
