import { routes } from "@/components/Modules/Workflows/WorkflowInputs/config/routes";
import { WorkflowInput } from "@/types";
import { ListController, useControls } from "quasar-ui-danx";

export const controls = useControls("workflow-inputs", {
	label: "Workflow Inputs",
	routes: routes
}) as ListController<WorkflowInput>;
