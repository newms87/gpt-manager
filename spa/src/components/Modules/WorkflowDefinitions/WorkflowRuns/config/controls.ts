import { WorkflowRun } from "@/types";
import { ListController, useControls } from "quasar-ui-danx";
import { routes } from "./routes";

export const controls = useControls("workflow-runs", {
	label: "Workflow Runs",
	routes
}) as ListController<WorkflowRun>;
