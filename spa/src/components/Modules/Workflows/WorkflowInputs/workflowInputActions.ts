import { WorkflowInputController } from "@/components/Modules/Workflows/WorkflowInputs/workflowInputControls";
import { WorkflowInputRoutes } from "@/routes/workflowInputRoutes";
import { ActionOptions, useActions, withDefaultActions } from "quasar-ui-danx";

// This is the default action options for all items
const forAllItems: Partial<ActionOptions> = {
	onAction: WorkflowInputRoutes.applyAction,
	onBatchAction: WorkflowInputRoutes.batchAction,
	onBatchSuccess: WorkflowInputController.clearSelectedRows
};

const items: ActionOptions[] = [
	...withDefaultActions("Workflow Inputs", WorkflowInputController)
];

export const { getAction, getActions } = useActions(items, forAllItems);
