import { WorkflowNode } from "@/types";
import { ListController, useControls } from "quasar-ui-danx";
import { routes } from "./routes";

export const controls = useControls("workflow-nodes", {
	label: "Workflow Nodes",
	routes
}) as ListController<WorkflowNode>;
