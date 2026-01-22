import { SchemaDefinition } from "@/types";
import { ActionPanel } from "quasar-ui-danx";
import { h } from "vue";
import { ArtifactCategoryDefinitionsPanel, SchemaDefinitionInfoPanel, SchemaDefinitionPanel } from "../Panels";

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
		name: "artifact-categories",
		label: "Artifact Categories",
		class: "w-[80vw]",
		vnode: (schemaDefinition: SchemaDefinition) => h(ArtifactCategoryDefinitionsPanel, { schemaDefinition })
	}
];
