import { ContentSourceController } from "@/components/Modules/ContentSources/contentSourceControls";
import { ContentSourceApiConfigPanel, ContentSourceInfoPanel } from "@/components/Modules/ContentSources/Panels";
import { ContentSource } from "@/types/content-sources";
import { ActionPanel } from "quasar-ui-danx/types";
import { computed, h } from "vue";

const activeItem = computed<ContentSource>(() => ContentSourceController.activeItem.value);

export const panels = computed<ActionPanel[]>(() => [
	{
		name: "edit",
		label: "Info",
		vnode: () => h(ContentSourceInfoPanel, {
			contentSource: activeItem.value
		})
	},
	{
		name: "api",
		label: "API Config",
		class: "w-[80em]",
		vnode: () => h(ContentSourceApiConfigPanel, {
			contentSource: activeItem.value
		})
	}
]);
