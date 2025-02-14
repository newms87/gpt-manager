import { TaskWorkflow } from "@/types/task-workflows";
import { ListController, useControls } from "quasar-ui-danx";
import { routes } from "./routes";

export const controls = useControls("task-workflows", {
	label: "Task Workflows",
	routes
}) as ListController<TaskWorkflow>;
