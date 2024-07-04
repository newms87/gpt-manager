import { getAction } from "@/components/Modules/Agents/agentActions";
import { AgentController } from "@/components/Modules/Agents/agentControls";
import {
	AgentAssignmentsPanel,
	AgentInfoPanel,
	AgentPromptPanel,
	AgentThreadsPanel,
	AgentToolsPanel
} from "@/components/Modules/Agents/Panels";
import { ActionPanel, BadgeTab } from "quasar-ui-danx";

import { Agent } from "src/types";
import { computed, h } from "vue";

const activeAgent = computed(() => AgentController.activeItem.value as Agent);
const updateAction = getAction("update");

export const panels = computed<ActionPanel[]>(() => [
	{
		name: "edit",
		label: "Details",
		vnode: () => h(AgentInfoPanel, {
			agent: activeAgent.value
		})
	},
	{
		name: "tools",
		label: "Tools",
		tabVnode: () => h(BadgeTab, { count: activeAgent.value?.tools?.length }),
		vnode: () => h(AgentToolsPanel, {
			agent: activeAgent.value
		})
	},
	{
		name: "prompt",
		label: "Prompt",
		class: "w-[60rem]",
		vnode: () => h(AgentPromptPanel, {
			agent: activeAgent.value,
			onChange: input => updateAction.trigger(activeAgent.value, input)
		})
	},
	{
		name: "threads",
		label: "Threads",
		class: "w-[60rem]",
		enabled: !!activeAgent.value,
		tabVnode: () => h(BadgeTab, { count: activeAgent.value?.threads_count }),
		vnode: () => h(AgentThreadsPanel, {
			agent: activeAgent.value
		})
	},
	{
		name: "assignments",
		label: "Assignments",
		class: "w-[60rem]",
		enabled: !!activeAgent.value,
		tabVnode: () => h(BadgeTab, { count: activeAgent.value?.assignments_count }),
		vnode: () => h(AgentAssignmentsPanel, {
			agent: activeAgent.value
		})
	}
]);
