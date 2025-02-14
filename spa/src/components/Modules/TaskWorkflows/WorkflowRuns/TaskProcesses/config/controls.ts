import { TaskProcess } from "@/types";
import { ListController, useControls } from "quasar-ui-danx";
import { routes } from "./routes";

export const controls = useControls("task-processes", {
	label: "Task Processes",
	routes
}) as ListController<TaskProcess>;
