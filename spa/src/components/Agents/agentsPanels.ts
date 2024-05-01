import { performAction } from "@/components/Agents/agentsActions";
import { AgentController } from "@/components/Agents/agentsControls";
import { AgentInfoPanel, AgentPromptPanel } from "@/components/Agents/Panels";
import { computed, h } from "vue";

const activeItem = computed(() => AgentController.activeItem.value);

export const panels = computed(() => [
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
	}
]);
