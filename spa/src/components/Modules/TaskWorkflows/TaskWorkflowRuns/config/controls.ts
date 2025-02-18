import { TaskWorkflowRun } from "@/types/task-workflows";
import { ListController, useControls } from "quasar-ui-danx";
import { routes } from "./routes";

export const controls = useControls("task-workflow-runs", {
	label: "Task Workflow Runs",
	routes
}) as ListController<TaskWorkflowRun>;
