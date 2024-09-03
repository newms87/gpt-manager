import { actionControls } from "./actions";
import { columns } from "./columns";
import { controls } from "./controls";
import { filters } from "./filters";
import { panels } from "./panels";
import { routes } from "./routes";

export const dxPromptDirective = {
	...controls,
	...actionControls,
	columns,
	filters,
	panels,
	routes
};
