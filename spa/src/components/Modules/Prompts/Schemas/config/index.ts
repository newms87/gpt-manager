import { ActionController } from "quasar-ui-danx";
import { actionControls, batchActions, menuActions } from "./actions";
import { columns } from "./columns";
import { controls, PromptSchemaControllerInterface } from "./controls";
import { fields } from "./fields";
import { filters } from "./filters";
import { panels } from "./panels";
import { routes } from "./routes";

export const dxPromptSchema = {
	...controls,
	...actionControls,
	menuActions,
	batchActions,
	columns,
	filters,
	fields,
	panels,
	routes
} as ActionController & PromptSchemaControllerInterface;
