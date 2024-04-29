import { AgentController } from "@/components/Agents/agentsControls";
import AgentInfo from "@/components/Agents/Panels/AgentInfo";
import { computed, h } from "vue";

export const panels = computed(() => [
	{
		name: "edit",
		label: "Details",
		vnode: () => h(AgentInfo, { agent: AgentController.activeItem.value })
	}
]);
