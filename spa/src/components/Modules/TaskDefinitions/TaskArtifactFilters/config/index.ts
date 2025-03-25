import { TaskArtifactFilter } from "@/types";
import { DanxController } from "quasar-ui-danx";
import { actionControls } from "./actions";
import { controls } from "./controls";

export const dxTaskArtifactFilter = {
	...controls,
	...actionControls
} as DanxController<TaskArtifactFilter>;
