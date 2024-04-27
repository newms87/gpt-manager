import { activeItem } from "@/components/Agents/agentsControls";
import { computed, h } from "vue";

export const panels = computed(() => [
    {
        name: "edit-agent",
        label: "Edit Agent",
        vnode: () => h("div", "Edit Agent " + activeItem.value.name)
    }
]);
