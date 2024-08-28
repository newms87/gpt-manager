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
				type: "date-range",
				name: "updated_at",
				label: "Updated Date"
			},
			{
				type: "text",
				name: "keywords",
				label: "Search"
			}
		]
	}
]);
