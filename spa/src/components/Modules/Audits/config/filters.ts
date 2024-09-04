import { computed } from "vue";
import { controls } from "./controls";

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
				name: "requestMethod",
				label: "Method",
				placeholder: "(All Methods)",
				options: ["GET", "POST", "PUT", "PATCH", "DELETE"]
			},
			{
				type: "single-select",
				name: "url",
				label: "URL",
				options: controls.getFieldOptions("urls")
			}
		]
	}
]);
