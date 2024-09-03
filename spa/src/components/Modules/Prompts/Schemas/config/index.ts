import { actionControls } from "./actions";
import { columns } from "./columns";
import { controls } from "./controls";
import { fields } from "./fields";
import { filters } from "./filters";
import { panels } from "./panels";
import { routes } from "./routes";

export const dxPromptSchema = {
	...controls,
	...actionControls,
	columns,
	filters,
	fields,
	panels,
	routes
};
