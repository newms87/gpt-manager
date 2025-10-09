import { FaSolidBookOpen, FaSolidClipboard, FaSolidFile } from "danx-icon";
import { markRaw } from "vue";
import type { UiNavigation } from "../ui/shared/types";

export const uiNavigation: UiNavigation[] = [
    {
        title: "My Demands",
        icon: markRaw(FaSolidFile),
        route: "/ui/demands"
    },
    {
        title: "Demand Templates",
        icon: markRaw(FaSolidClipboard),
        route: "/ui/templates"
    },
    {
        title: "Instructions",
        icon: markRaw(FaSolidBookOpen),
        route: "/ui/instruction-templates"
    }
];
