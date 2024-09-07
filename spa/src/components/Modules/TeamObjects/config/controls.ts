import { TeamObject } from "@/components/Modules/TeamObjects/team-objects";
import { ListController, PagedItems, useControls } from "quasar-ui-danx";
import type { ShallowRef } from "vue";
import { routes } from "./routes";

export interface TeamObjectPagedItems extends PagedItems {
	data: TeamObject[];
}

export interface TeamObjectControllerInterface extends ListController {
	activeItem: ShallowRef<TeamObject>;
	pagedItems: ShallowRef<TeamObjectPagedItems>;
}

export const controls = useControls("team-objects", {
	label: "Team Objects",
	routes
}) as TeamObjectControllerInterface;
