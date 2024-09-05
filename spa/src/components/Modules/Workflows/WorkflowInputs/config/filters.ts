import { computed } from "vue";
import { controls } from "./controls";

export const filters = computed(() => [
	{
		name: "General",
		flat: true,
		fields: [
			{
				type: "text",
				name: "keywords",
				label: "Search"
			},
			{
				type: "date-range",
				name: "created_at",
				label: "Created Date"
			},
			{
				type: "date-range",
				name: "updated_at",
				label: "Updated Date"
			},
			{
				type: "multi-select",
				name: "objectTagTaggables.object_tag_id",
				label: "Tags",
				placeholder: "(Select Tags)",
				options: controls.getFieldOptions("tags")
			}
		]
	}
]);
