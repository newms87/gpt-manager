import {
	PromptSchemaAgentsPanel,
	PromptSchemaDefinitionPanel,
	PromptSchemaInfoPanel
} from "@/components/Modules/Prompts/Schemas/Panels";
import { PromptSchema } from "@/types";
import { ActionPanel, BadgeTab } from "quasar-ui-danx";
import { computed, h } from "vue";

export const panels = computed<ActionPanel[]>(() => [
	{
		name: "edit",
		label: "Details",
		vnode: (promptSchema: PromptSchema) => h(PromptSchemaInfoPanel, { promptSchema })
	},
	{
		name: "definition",
		label: "Definition",
		vnode: (promptSchema: PromptSchema) => h(PromptSchemaDefinitionPanel, { promptSchema })
	},
	{
		name: "agents",
		label: "Agents",
		tabVnode: (promptSchema: PromptSchema) => h(BadgeTab, { count: promptSchema.agents_count }),
		vnode: (promptSchema: PromptSchema) => h(PromptSchemaAgentsPanel, { promptSchema })
	}
]);
