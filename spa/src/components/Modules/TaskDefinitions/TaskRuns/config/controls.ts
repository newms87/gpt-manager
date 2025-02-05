import { TaskRun } from "@/types";
import { ListController, useControls } from "quasar-ui-danx";
import { routes } from "./routes";

export const controls = useControls("task-runs", {
	label: "Task Runs",
	routes
}) as ListController<TaskRun>;
