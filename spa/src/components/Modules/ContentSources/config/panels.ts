import { ContentSourceApiConfigPanel, ContentSourceInfoPanel } from "@/components/Modules/ContentSources/Panels";
import { ContentSource } from "@/types/content-sources";
import { h } from "vue";

export const panels = [
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
];
