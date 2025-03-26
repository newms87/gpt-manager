import { Agent, JsonSchema, JsonSchemaType } from "@/types";
import { ActionTargetItem, ListControlsRoutes } from "quasar-ui-danx";

export interface SchemaDefinition extends ActionTargetItem {
	name: string;
	description: string;
	schema_format: "text" | "json" | "yaml" | "ts";
	schema: JsonSchema;
	response_example: object | object[];
	agents: Agent[];
	can: {
		view: boolean;
		edit: boolean;
	};
}

export interface SchemaFragment extends ActionTargetItem {
	id: string;
	schema_definition_id: string;
	name: string;
	fragment_selector: FragmentSelector;
}

export interface SchemaAssociation extends ActionTargetItem {
	id: string;
	schema: SchemaDefinition;
	fragment?: SchemaFragment;
}

export interface FragmentSelector {
	schema_definition_id?: string;
	type: JsonSchemaType;
	create?: boolean;
	update?: boolean;
	children?: {
		[key: string]: FragmentSelector;
	};
}

export interface PromptDirective extends ActionTargetItem {
	id: string;
	name: string;
	directive_text: string;
	agents_count: number;
}

export interface AgentPromptDirective extends ActionTargetItem {
	id: string;
	directive: PromptDirective;
	position: number;
	section: string;
}

export interface SchemaDefinitionRevision {
	id: number;
	schema: SchemaDefinition;
	user_email: string;
	created_at: string;
}

export interface SchemaDefinitionRoutes extends ListControlsRoutes<SchemaDefinition> {
	history(target: SchemaDefinition): Promise<SchemaDefinitionRevision[]>;
}
