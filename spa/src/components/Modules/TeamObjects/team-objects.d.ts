import { ThreadMessage } from "@/types";
import { ActionTargetItem, UploadedFile } from "quasar-ui-danx";

interface TeamObject extends ActionTargetItem {
	id: number;
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


interface TeamObjectAttribute {
	id: string;
	name: string;
	date: Date;
	value: object | string | boolean | number | Date | string[] | object[] | null;
	description: string;
	confidence: string;
	source?: UploadedFile;
	sourceMessages?: ThreadMessage[];
	thread_url?: string;
	created_at: Date;
	updated_at: Date;
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
	format?: "boolean" | "shortCurrency" | "number" | "date" | "list";
}

export interface TeamObjectAttributeProps extends TeamObjectAttributeBlockProps {
	name: string;
	title?: string;
	object: TeamObject;
}
