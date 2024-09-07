import { TeamObject } from "@/components/Modules/TeamObjects/team-objects";
import { DanxController } from "quasar-ui-danx";
import { actionControls } from "./actions";
import { controls } from "./controls";
import { fields } from "./fields";
import { filters } from "./filters";
import { routes } from "./routes";

export const dxTeamObject = {
	...controls,
	...actionControls,
	filters,
	fields,
	routes
} as DanxController<TeamObject>;
