import { WorkflowConnection } from "@/types";
import { ListController, useControls } from "quasar-ui-danx";
import { routes } from "./routes";

export const controls = useControls("workflow-connections", {
	label: "Workflow Connections",
	routes
}) as ListController<WorkflowConnection>;
