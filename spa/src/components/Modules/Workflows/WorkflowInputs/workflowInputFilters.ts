import { WorkflowInputController } from "@/components/Modules/Workflows/WorkflowInputs/workflowInputControls";
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
				name: "objectTagTaggables.object_tag_id",
				label: "Tags",
				placeholder: "(Select Tags)",
				options: WorkflowInputController.getFieldOptions("tags")
			}
		]
	}
]);
