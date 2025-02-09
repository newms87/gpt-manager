import { SchemaAssociation } from "@/types";
import { ListController, useControls } from "quasar-ui-danx";
import { routes } from "./routes";

export const controls = useControls("schema.associations", {
	label: "Schema Associations",
	routes
}) as ListController<SchemaAssociation>;
