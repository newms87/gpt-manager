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
};
