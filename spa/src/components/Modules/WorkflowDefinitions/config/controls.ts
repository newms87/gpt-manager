import { WorkflowDefinition } from "@/types";
import { ListController, useControls } from "quasar-ui-danx";
import { routes } from "./routes";

export const controls = useControls("workflows-definitions", {
	label: "Workflow Definitions",
	routes
}) as ListController<WorkflowDefinition>;
