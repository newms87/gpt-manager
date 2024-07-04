import { WorkflowInfoPanel, WorkflowJobsPanel, WorkflowRunsPanel } from "@/components/Modules/Workflows/Panels";
import { Workflow } from "@/types";
import { ActionPanel, BadgeTab } from "quasar-ui-danx";
import { computed, h } from "vue";

export const panels = computed<ActionPanel[]>(() => [
	{
		name: "edit",
		label: "Details",
		vnode: (workflow: Workflow) => h(WorkflowInfoPanel, { workflow })
	},
	{
		name: "jobs",
		label: "Jobs",
		class: "w-[80rem]",
		tabVnode: (workflow: Workflow) => h(BadgeTab, { count: workflow.jobs_count }),
		vnode: (workflow: Workflow) => h(WorkflowJobsPanel, { workflow })
	},
	{
		name: "runs",
		label: "Runs",
		class: "w-[80rem]",
		tabVnode: (workflow: Workflow) => h(BadgeTab, { count: workflow.runs_count }),
		vnode: (workflow: Workflow) => h(WorkflowRunsPanel, { workflow })
	}
]);
