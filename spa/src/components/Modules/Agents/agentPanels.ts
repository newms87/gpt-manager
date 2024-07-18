import {
	AgentAssignmentsPanel,
	AgentInfoPanel,
	AgentPromptPanel,
	AgentResponsePanel,
	AgentThreadsPanel,
	AgentToolsPanel
} from "@/components/Modules/Agents/Panels";
import { ActionPanel, BadgeTab } from "quasar-ui-danx";
import { Agent } from "src/types";
import { computed, h } from "vue";

export const panels = computed<ActionPanel[]>(() => [
	{
		name: "edit",
		label: "Details",
		vnode: (agent: Agent) => h(AgentInfoPanel, { agent })
	},
	{
		name: "tools",
		label: "Tools",
		tabVnode: (agent: Agent) => h(BadgeTab, { count: agent.tools.length }),
		vnode: (agent: Agent) => h(AgentToolsPanel, { agent })
	},
	{
		name: "prompt",
		label: "Prompt",
		vnode: (agent: Agent) => h(AgentPromptPanel, { agent })
	},
	{
		name: "response",
		label: "Response",
		vnode: (agent: Agent) => h(AgentResponsePanel, { agent })
	},
	{
		name: "threads",
		label: "Threads",
		tabVnode: (agent: Agent) => h(BadgeTab, { count: agent.threads_count }),
		vnode: (agent: Agent) => h(AgentThreadsPanel, { agent })
	},
	{
		name: "assignments",
		label: "Assignments",
		tabVnode: (agent: Agent) => h(BadgeTab, { count: agent.assignments_count }),
		vnode: (agent: Agent) => h(AgentAssignmentsPanel, { agent })
	}
]);
