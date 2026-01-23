import { SchemaDefinition } from "@/types";
import { DanxController } from "quasar-ui-danx";
import { actionControls, batchActions, menuActions } from "./actions";
import { controls } from "./controls";
import { routes } from "./routes";

export const dxSchemaDefinition = {
	...controls,
	...actionControls,
	menuActions,
	batchActions,
	routes
} as DanxController<SchemaDefinition>;
