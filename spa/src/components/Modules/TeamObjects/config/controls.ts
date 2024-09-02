import { TeamObject } from "@/components/Modules/TeamObjects/team-objects";
import { ActionController, PagedItems, useListControls } from "quasar-ui-danx";
import type { ShallowRef } from "vue";
import { TeamObjectRoutes } from "./routes";

export interface TeamObjectPagedItems extends PagedItems {
	data: TeamObject[];
}

export interface TeamObjectControllerInterface extends ActionController {
	activeItem: ShallowRef<TeamObject>;
	pagedItems: ShallowRef<TeamObjectPagedItems>;
}

export const dxTeamObject: TeamObjectControllerInterface = useListControls("team-objects", {
	label: "Team Objects",
	routes: TeamObjectRoutes
}) as TeamObjectControllerInterface;
