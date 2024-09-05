import {
	WorkflowInputInfoPanel,
	WorkflowInputInputPanel,
	WorkflowInputWorkflowRunsPanel
} from "@/components/Modules/Workflows/WorkflowInputs/Panels";
import { WorkflowInput } from "@/types/workflow-inputs";
import { ActionPanel, BadgeTab } from "quasar-ui-danx";
import { h } from "vue";

export const panels: ActionPanel[] = [
	{
		name: "edit",
		label: "Info",
		vnode: (workflowInput: WorkflowInput) => h(WorkflowInputInfoPanel, { workflowInput })
	},
	{
		name: "input",
		label: "Input",
		vnode: (workflowInput: WorkflowInput) => h(WorkflowInputInputPanel, { workflowInput })
	},
	{
		name: "runs",
		label: "Workflow Runs",
		tabVnode: (workflowInput: WorkflowInput) => h(BadgeTab, { count: workflowInput.workflow_runs_count }),
		vnode: (workflowInput: WorkflowInput) => h(WorkflowInputWorkflowRunsPanel, { workflowInput })
	}
];
