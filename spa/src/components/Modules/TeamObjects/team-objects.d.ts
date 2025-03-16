import { AgentThreadMessage } from "@/types";
import { ActionTargetItem, UploadedFile } from "quasar-ui-danx";

interface TeamObject extends ActionTargetItem {
	id: number;
	schema_definition_id: number;
	type: string;
	name: string;
	description: string | null;
	url: string | null;
	meta?: object | null;
	relations: {
		[key: string]: TeamObject[];
	};
	attributes: {
		[key: string]: TeamObjectAttribute;
	};
}


interface TeamObjectAttribute extends ActionTargetItem {
	id: string;
	name: string;
	date: string;
	value: object | string | boolean | number | Date | string[] | object[] | null;
	confidence: string;
	reason: string;
	sources?: TeamObjectAttributeSource[];
	thread_url?: string;
	created_at: string;
	updated_at: string;
}

interface TeamObjectAttributeSource {
	id: number;
	source_type: string;
	source_id: string;
	explanation: string;
	sourceFile?: UploadedFile;
	sourceMessage?: AgentThreadMessage;
	created_at: string;
}

interface BooleanAttribute extends TeamObjectAttribute {
	value: boolean | null;
}

interface StringAttribute extends TeamObjectAttribute {
	value: number | null;
}

interface NumberAttribute extends TeamObjectAttribute {
	value: number | null;
}

interface DateAttribute extends TeamObjectAttribute {
	value: string | null;
}

interface ObjectAttribute extends TeamObjectAttribute {
	value: object | null;
}

interface StringArrayAttribute extends TeamObjectAttribute {
	value: string[] | null;
}

interface ObjectArrayAttribute extends TeamObjectAttribute {
	value: object[] | null;
}

export interface TeamObjectAttributeBlockProps {
	label?: string;
	attribute?: TeamObjectAttribute;
	format?: "boolean" | "shortCurrency" | "number" | "date" | "list" | "date-time" | "string";
}

export interface TeamObjectAttributeProps extends TeamObjectAttributeBlockProps {
	name: string;
	title?: string;
	object: TeamObject;
}

export interface TeamObjectAttributeSourceCardProps {
	source: TeamObjectAttributeSource;
}
