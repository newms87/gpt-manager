import { TeamObject } from "@/components/Modules/TeamObjects/team-objects";
import { ListController, useControls } from "quasar-ui-danx";
import { routes } from "./routes";

export const controls = useControls("team-objects", {
	label: "Team Objects",
	routes
}) as ListController<TeamObject>;
