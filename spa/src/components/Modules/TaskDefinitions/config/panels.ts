import { TaskDefinitionInfoPanel, TaskDefinitionTaskRunsPanel } from "@/components/Modules/TaskDefinitions";
import { TaskDefinition } from "@/types";
import { BadgeTab } from "quasar-ui-danx";
import { h } from "vue";

export const panels = [
	{
		name: "edit",
		label: "Details",
		vnode: (taskDefinition: TaskDefinition) => h(TaskDefinitionInfoPanel, { taskDefinition })
	},
	{
		name: "task_runs",
		label: "Task Runs",
		tabVnode: (taskDefinition: TaskDefinition) => h(BadgeTab, { count: taskDefinition.task_run_count }),
		vnode: (taskDefinition: TaskDefinition) => h(TaskDefinitionTaskRunsPanel, { taskDefinition })
	}
];
