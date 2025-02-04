import { AgentThreadsPanel } from "@/components/Modules/Agents/Panels";
import { TaskDefinitionInfoPanel } from "@/components/Modules/TaskDefinitions";
import { Agent } from "@/types";
import { BadgeTab } from "quasar-ui-danx";
import { h } from "vue";

export const panels = [
	{
		name: "edit",
		label: "Details",
		vnode: (agent: Agent) => h(TaskDefinitionInfoPanel, { agent })
	},
	{
		name: "task_runs",
		label: "Task Runs",
		tabVnode: (agent: Agent) => h(BadgeTab, { count: agent.threads_count }),
		vnode: (agent: Agent) => h(AgentThreadsPanel, { agent })
	}
];
