import { getAction } from "@/components/Modules/Agents/agentActions";
import { AgentController } from "@/components/Modules/Agents/agentControls";
import {
	AgentAssignmentsPanel,
	AgentInfoPanel,
	AgentPromptPanel,
	AgentThreadsPanel,
	AgentToolsPanel
} from "@/components/Modules/Agents/Panels";
import { BadgeTab } from "quasar-ui-danx";
import { ActionPanel } from "quasar-ui-danx/types";
import { computed, h } from "vue";

const activeItem = computed(() => AgentController.activeItem.value);
const updateAction = getAction("update");

export const panels = computed<ActionPanel[]>(() => [
	{
		name: "edit",
		label: "Details",
		vnode: () => h(AgentInfoPanel, {
			agent: activeItem.value
		})
	},
	{
		name: "tools",
		label: "Tools",
		tabVnode: () => h(BadgeTab, { count: activeItem.value?.tools?.length }),
		vnode: () => h(AgentToolsPanel, {
			agent: activeItem.value
		})
	},
	{
		name: "prompt",
		label: "Prompt",
		class: "w-[60rem]",
		vnode: () => h(AgentPromptPanel, {
			agent: activeItem.value,
			onChange: input => updateAction.trigger(activeItem.value, input)
		})
	},
	{
		name: "threads",
		label: "Threads",
		class: "w-[60rem]",
		enabled: !!activeItem.value,
		tabVnode: () => h(BadgeTab, { count: activeItem.value?.threads_count }),
		vnode: () => h(AgentThreadsPanel, {
			agent: activeItem.value
		})
	},
	{
		name: "assignments",
		label: "Assignments",
		class: "w-[60rem]",
		enabled: !!activeItem.value,
		tabVnode: () => h(BadgeTab, { count: activeItem.value?.assignments_count }),
		vnode: () => h(AgentAssignmentsPanel, {
			agent: activeItem.value
		})
	}
]);
