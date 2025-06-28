import { AgentInfoPanel, AgentThreadsPanel } from "@/components/Modules/Agents/Panels";
import { Agent } from "@/types";
import { BadgeTab } from "quasar-ui-danx";
import { h } from "vue";

export const panels = [
	{
		name: "edit",
		label: "Details",
		vnode: (agent: Agent) => h(AgentInfoPanel, { agent })
	},
	{
		name: "threads",
		label: "Threads",
		tabVnode: (agent: Agent) => h(BadgeTab, { count: agent.threads_count }),
		vnode: (agent: Agent) => h(AgentThreadsPanel, { agent })
	}
];
