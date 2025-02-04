import { TaskDefinition } from "@/types";
import { ListController, useControls } from "quasar-ui-danx";
import { routes } from "./routes";

export const controls = useControls("task-definitions", {
	label: "Task Definitions",
	routes
}) as ListController<TaskDefinition>;
