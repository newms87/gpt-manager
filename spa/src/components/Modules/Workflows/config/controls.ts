import { Workflow } from "@/types";
import { ListController, useControls } from "quasar-ui-danx";
import { routes } from "./routes";

export const controls = useControls("workflows", {
	label: "Workflows",
	routes
}) as ListController<Workflow>;
