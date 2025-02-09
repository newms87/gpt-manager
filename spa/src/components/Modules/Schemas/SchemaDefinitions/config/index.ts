import { SchemaDefinition } from "@/types";
import { DanxController } from "quasar-ui-danx";
import { actionControls, batchActions, menuActions } from "./actions";
import { columns } from "./columns";
import { controls } from "./controls";
import { fields } from "./fields";
import { filters } from "./filters";
import { panels } from "./panels";
import { routes } from "./routes";

export const dxSchemaDefinition = {
	...controls,
	...actionControls,
	menuActions,
	batchActions,
	columns,
	filters,
	fields,
	panels,
	routes
} as DanxController<SchemaDefinition>;
