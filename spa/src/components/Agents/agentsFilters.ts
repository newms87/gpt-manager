import { filterFieldOptions } from "@/components/Agents/agentsControls";
import { computed } from "vue";

export const filterFields = computed(() => [
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
                placeholder: "All Models",
                options: filterFieldOptions.value["models"]
            }
        ]
    }
]);
