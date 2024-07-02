import { WorkflowInfoPanel, WorkflowJobsPanel, WorkflowRunsPanel } from "@/components/Modules/Workflows/Panels";
import { WorkflowController } from "@/components/Modules/Workflows/workflowControls";
import { BadgeTab } from "quasar-ui-danx";
import { ActionPanel } from "quasar-ui-danx";
import { computed, h } from "vue";

const activeItem = computed(() => WorkflowController.activeItem.value);

export const panels = computed<ActionPanel[]>(() => [
	{
		name: "edit",
		label: "Details",
		vnode: () => h(WorkflowInfoPanel, {
			workflow: activeItem.value
		})
	},
	{
		name: "jobs",
		label: "Jobs",
		class: "w-[80rem]",
		tabVnode: () => h(BadgeTab, { count: activeItem.value.jobs_count }),
		vnode: () => h(WorkflowJobsPanel, {
			workflow: activeItem.value
		})
	},
	{
		name: "runs",
		label: "Runs",
		class: "w-[80rem]",
		tabVnode: () => h(BadgeTab, { count: activeItem.value.runs_count }),
		vnode: () => h(WorkflowRunsPanel, {
			workflow: activeItem.value
		})
	}
]);
