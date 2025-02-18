import { TaskWorkflowNode } from "@/types/task-workflows";
import { ListController, useControls } from "quasar-ui-danx";
import { routes } from "./routes";

export const controls = useControls("task-workflow-nodes", {
	label: "Task Workflow Nodes",
	routes
}) as ListController<TaskWorkflowNode>;
