import { SchemaDefinition } from "@/types";
import { ListController, useControls } from "quasar-ui-danx";
import { routes } from "./routes";

export const controls = useControls("schema-definitions", {
	label: "Schema Definitions",
	routes
}) as ListController<SchemaDefinition>;
