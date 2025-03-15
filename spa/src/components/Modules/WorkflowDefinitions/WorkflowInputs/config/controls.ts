import { WorkflowInput } from "@/types";
import { ListController, useControls } from "quasar-ui-danx";
import { routes } from "./routes";

export const controls = useControls("workflow-inputs", {
	label: "Workflow Inputs",
	routes: routes
}) as ListController<WorkflowInput>;
