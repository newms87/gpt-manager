import { SchemaAssociation } from "@/types";
import { DanxController } from "quasar-ui-danx";
import { actionControls } from "./actions";
import { controls } from "./controls";
import { routes } from "./routes";

export const dxSchemaAssociation = {
	...controls,
	...actionControls,
	routes
} as DanxController<SchemaAssociation>;
