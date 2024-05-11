import { WorkflowInfoPanel, WorkflowJobsPanel, WorkflowRunsPanel } from "@/components/Workflows/Panels";
import { WorkflowController } from "@/components/Workflows/workflowControls";
import { BadgeTab } from "quasar-ui-danx";
import { ActionPanel } from "quasar-ui-danx/types";
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
		class: "w-[60rem]",
		tabVnode: () => h(BadgeTab, { count: activeItem.value.jobs?.length }),
		vnode: () => h(WorkflowJobsPanel, {
			workflow: activeItem.value
		})
	},
	{
		name: "runs",
		label: "Runs",
		tabVnode: () => h(BadgeTab, { count: activeItem.value.runs?.length }),
		vnode: () => h(WorkflowRunsPanel, {
			workflow: activeItem.value
		})
	}
]);
