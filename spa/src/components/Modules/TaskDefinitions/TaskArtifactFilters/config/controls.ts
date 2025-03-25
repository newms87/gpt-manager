import { TaskArtifactFilter } from "@/types";
import { ListController, useControls } from "quasar-ui-danx";
import { routes } from "./routes";

export const controls = useControls("task-artifact-filters", {
	label: "Task Artifact Filters",
	routes
}) as ListController<TaskArtifactFilter>;
