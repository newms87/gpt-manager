import { performAction } from "@/components/Agents/agentsActions";
import { AgentController } from "@/components/Agents/agentsControls";
import AgentInfo from "@/components/Agents/Panels/AgentInfo";
import { computed, h } from "vue";

const activeItem = computed(() => AgentController.activeItem.value);

export const panels = computed(() => [
	{
		name: "edit",
		label: "Details",
		vnode: () => h(AgentInfo, {
			agent: activeItem.value,
			onChange: input => performAction("update", activeItem.value, input)
		})
	}
]);
