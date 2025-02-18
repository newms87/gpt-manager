import { TaskWorkflowConnection } from "@/types/task-workflows";
import { ListController, useControls } from "quasar-ui-danx";
import { routes } from "./routes";

export const controls = useControls("task-workflow-connections", {
	label: "Task Workflow Connections",
	routes
}) as ListController<TaskWorkflowConnection>;
