import { TaskDefinitionAgent } from "@/types";
import { ListController, useControls } from "quasar-ui-danx";
import { routes } from "./routes";

export const controls = useControls("task-definition-agents", {
	routes
}) as ListController<TaskDefinitionAgent>;
