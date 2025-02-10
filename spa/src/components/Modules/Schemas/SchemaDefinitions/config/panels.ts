import { SchemaDefinition } from "@/types";
import { ActionPanel, BadgeTab } from "quasar-ui-danx";
import { h } from "vue";
import { SchemaDefinitionAgentsPanel, SchemaDefinitionInfoPanel, SchemaDefinitionPanel } from "../Panels";

export const panels: ActionPanel[] = [
	{
		name: "edit",
		label: "Details",
		class: "w-[80vw]",
		vnode: (schemaDefinition: SchemaDefinition) => h(SchemaDefinitionInfoPanel, { schemaDefinition })
	},
	{
		name: "definition",
		label: "Definition",
		class: "w-[80vw]",
		vnode: (schemaDefinition: SchemaDefinition) => h(SchemaDefinitionPanel, { schemaDefinition })
	},
	{
		name: "agents",
		label: "Agents",
		class: "w-[80vw]",
		tabVnode: (schemaDefinition: SchemaDefinition) => h(BadgeTab, { count: schemaDefinition.agents_count }),
		vnode: (schemaDefinition: SchemaDefinition) => h(SchemaDefinitionAgentsPanel, { schemaDefinition })
	}
];
