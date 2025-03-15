import { FilterGroup } from "quasar-ui-danx";
import { computed } from "vue";

export const filters = computed<FilterGroup[]>(() => [
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
				label: "Created Date",
				inline: true
			}
		]
	}
]);
