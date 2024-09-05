import { WorkflowInfoPanel, WorkflowJobsPanel, WorkflowRunsPanel } from "@/components/Modules/Workflows/Panels";
import { Workflow } from "@/types";
import { ActionPanel, BadgeTab } from "quasar-ui-danx";
import { h } from "vue";

export const panels: ActionPanel[] = [
	{
		name: "edit",
		label: "Details",
		vnode: (workflow: Workflow) => h(WorkflowInfoPanel, { workflow })
	},
	{
		name: "jobs",
		label: "Jobs",
		tabVnode: (workflow: Workflow) => h(BadgeTab, { count: workflow.jobs_count }),
		vnode: (workflow: Workflow) => h(WorkflowJobsPanel, { workflow })
	},
	{
		name: "runs",
		label: "Runs",
		tabVnode: (workflow: Workflow) => h(BadgeTab, { count: workflow.runs_count }),
		vnode: (workflow: Workflow) => h(WorkflowRunsPanel, { workflow })
	}
];
