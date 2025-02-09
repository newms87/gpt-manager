import { SchemaFragment } from "@/types";
import { ListController, useControls } from "quasar-ui-danx";
import { routes } from "./routes";

export const controls = useControls("schemas.fragments", {
	label: "Schema Fragments",
	routes
}) as ListController<SchemaFragment>;
