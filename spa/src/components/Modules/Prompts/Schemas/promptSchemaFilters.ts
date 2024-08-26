import { AgentController } from "@/components/Modules/Agents/agentControls";
import { computed } from "vue";

export const filters = computed(() => [
	{
		name: "General",
		flat: true,
		fields: [
			{
				type: "date-range",
				name: "created_at",
				label: "Created Date",
				inline: true
			},
			{
				type: "multi-select",
				name: "model",
				label: "AI Model",
				options: AgentController.getFieldOptions("aiModels")
			}
		]
	}
]);
