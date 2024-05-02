import { performAction } from "@/components/Agents/agentsActions";
import { AgentController } from "@/components/Agents/agentsControls";
import { AgentInfoPanel, AgentPromptPanel } from "@/components/Agents/Panels";
import AgentThreadsPanel from "@/components/Agents/Panels/AgentThreadsPanel";
import { ActionPanel } from "quasar-ui-danx";
import { computed, ComputedRef, h } from "vue";

const activeItem = computed(() => AgentController.activeItem.value);

export const panels: ComputedRef<ActionPanel[]> = computed(() => [
	{
		name: "edit",
		label: "Details",
		vnode: () => h(AgentInfoPanel, {
			agent: activeItem.value,
			onChange: input => performAction("update", activeItem.value, input)
		})
	},
	{
		name: "prompt",
		label: "Prompt",
		vnode: () => h(AgentPromptPanel, {
			agent: activeItem.value,
			onChange: input => performAction("update", activeItem.value, input)
		})
	},
	{
		name: "threads",
		label: "Threads",
		class: "w-[60rem]",
		enabled: !!activeItem.value,
		vnode: () => h(AgentThreadsPanel, {
			agent: activeItem.value,
			onChange: input => performAction("update", activeItem.value, input)
		})
	}
]);
