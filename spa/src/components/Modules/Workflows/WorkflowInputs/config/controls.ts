import { routes } from "@/components/Modules/Workflows/WorkflowInputs/config/routes";
import { WorkflowInput } from "@/types";
import { ListController, PagedItems, useControls } from "quasar-ui-danx";
import type { ShallowRef } from "vue";

export interface WorkflowInputPagedItems extends PagedItems {
	data: WorkflowInput[];
}

export interface WorkflowInputControllerInterface extends ListController {
	activeItem: ShallowRef<WorkflowInput>;
	pagedItems: ShallowRef<WorkflowInputPagedItems>;
}

export const controls = useControls("workflow-inputs", {
	label: "Workflow Inputs",
	routes: routes
}) as WorkflowInputControllerInterface;
