import {
	PromptDirectiveAgentsPanel,
	PromptDirectiveDefinitionPanel
} from "@/components/Modules/Prompts/Directives/Panels";
import { PromptDirective } from "@/types";
import { ActionPanel, BadgeTab } from "quasar-ui-danx";
import { computed, h } from "vue";

export const panels = computed<ActionPanel[]>(() => [
	{
		name: "edit",
		label: "Definition",
		vnode: (promptDirective: PromptDirective) => h(PromptDirectiveDefinitionPanel, { promptDirective })
	},
	{
		name: "agents",
		label: "Agents",
		tabVnode: (promptDirective: PromptDirective) => h(BadgeTab, { count: promptDirective.agents_count }),
		vnode: (promptDirective: PromptDirective) => h(PromptDirectiveAgentsPanel, { promptDirective })
	}
]);