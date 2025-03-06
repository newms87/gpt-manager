import { WorkflowInput } from "@/types";
import { ActionPanel } from "quasar-ui-danx";
import { h } from "vue";
import { WorkflowInputInfoPanel, WorkflowInputInputPanel } from "../Panels";

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
	}
];
