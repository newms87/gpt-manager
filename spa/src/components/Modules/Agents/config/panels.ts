import {
	AgentInfoPanel,
	AgentPromptPanel,
	AgentThreadsPanel,
	AgentToolsPanel
} from "@/components/Modules/Agents/Panels";
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
		name: "tools",
		label: "Tools",
		tabVnode: (agent: Agent) => h(BadgeTab, { count: agent.tools.length }),
		vnode: (agent: Agent) => h(AgentToolsPanel, { agent })
	},
	{
		name: "prompt",
		label: "Prompt",
		class: "w-[80vw]",
		vnode: (agent: Agent) => h(AgentPromptPanel, { agent })
	},
	{
		name: "threads",
		label: "Threads",
		tabVnode: (agent: Agent) => h(BadgeTab, { count: agent.threads_count }),
		vnode: (agent: Agent) => h(AgentThreadsPanel, { agent })
	}
];
