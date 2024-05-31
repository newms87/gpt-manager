import {
	WorkflowInputInfoPanel,
	WorkflowInputInputPanel,
	WorkflowInputWorkflowRunsPanel
} from "@/components/Modules/Workflows/WorkflowInputs/Panels";
import { WorkflowInputController } from "@/components/Modules/Workflows/WorkflowInputs/workflowInputControls";
import { WorkflowInput } from "@/types/workflow-inputs";
import { BadgeTab } from "quasar-ui-danx";
import { ActionPanel } from "quasar-ui-danx/types";
import { computed, h } from "vue";

const activeItem = computed<WorkflowInput>(() => WorkflowInputController.activeItem.value);

export const panels = computed<ActionPanel[]>(() => [
	{
		name: "edit",
		label: "Info",
		vnode: () => h(WorkflowInputInfoPanel, { workflowInput: activeItem.value })
	},
	{
		name: "input",
		label: "Input",
		vnode: () => h(WorkflowInputInputPanel, { workflowInput: activeItem.value })
	},
	{
		name: "runs",
		label: "Workflow Runs",
		class: "w-[80rem] max-w-full",
		tabVnode: () => h(BadgeTab, { count: activeItem.value.workflow_runs_count }),
		vnode: () => h(WorkflowInputWorkflowRunsPanel, {
			workflowInput: activeItem.value
		})
	}
]);
