import { ContentSourceApiConfigPanel, ContentSourceInfoPanel } from "@/components/Modules/ContentSources/Panels";
import { ContentSource } from "@/types/content-sources";
import { ActionPanel } from "quasar-ui-danx";
import { computed, h } from "vue";

export const panels = computed<ActionPanel[]>(() => [
	{
		name: "edit",
		label: "Info",
		vnode: (contentSource: ContentSource) => h(ContentSourceInfoPanel, { contentSource })
	},
	{
		name: "api",
		label: "API Config",
		vnode: (contentSource: ContentSource) => h(ContentSourceApiConfigPanel, { contentSource })
	}
]);
