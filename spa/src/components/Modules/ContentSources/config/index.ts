import { ActionController } from "quasar-ui-danx";
import { actionControls, batchActions, menuActions } from "./actions";
import { columns } from "./columns";
import { ContentSourceControllerInterface, controls } from "./controls";
import { filters } from "./filters";
import { panels } from "./panels";
import { routes } from "./routes";

export const dxContentSource = {
	...controls,
	...actionControls,
	menuActions,
	batchActions,
	columns,
	filters,
	panels,
	routes
} as ActionController & ContentSourceControllerInterface;