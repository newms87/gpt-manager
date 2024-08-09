import { WorkflowRun } from "@/types";
import { TypedObject, UploadedFile } from "quasar-ui-danx";

interface TeamObject extends TypedObject {
	id: number;
	name: string;
	description: string | null;
	url: string | null;
	meta?: object | null;
}

interface TeamObjectAttribute {
	id: string;
	name: string;
	date: Date;
	value: object | string | boolean | number | Date | null;
	source?: UploadedFile;
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

interface DrugInjury extends TeamObject {
	evaluation_score?: NumberAttribute;
	severity_level?: NumberAttribute;
	hospitalization?: BooleanAttribute;
	surgical_procedure?: BooleanAttribute;
	permanent_disability?: BooleanAttribute;
	death?: BooleanAttribute;
	ongoing_care?: BooleanAttribute;
	economic_damage_min?: NumberAttribute;
	economic_damage_max?: NumberAttribute;
	product: DrugProduct;
	studies: ScientificStudy[];
	warnings: DrugWarning[];
	workflowRun?: WorkflowRun;
}

interface DrugProduct extends TeamObject {
	number_of_users: NumberAttribute;
	market_share: NumberAttribute;
	// The number of years the statute of limitations is tolled for this drug
	statute_of_limitations_tolling: StringAttribute;
	patents: Patent[];
	// DrugProducts can have multiple DrugGenerics (ie: Actos can be pioglitazone and metformin)
	generics: DrugGeneric[];
	company: Company;
}

interface DrugGeneric extends TeamObject {
	// DrugGenerics can have multiple DrugProducts (ie: acetaminophen can be in Tylenol, Excedrin, etc)
	products: DrugProduct[];
}

interface Company extends TeamObject {
	score: NumberAttribute;
	net_income: NumberAttribute;
	annual_revenue: NumberAttribute;
	operating_income: NumberAttribute;
	total_assets: NumberAttribute;
	total_equity: NumberAttribute;
}

interface Patent extends TeamObject {
	number: StringAttribute;
	filed_date: DateAttribute;
	expiration_date: DateAttribute;
	issued_date: DateAttribute;
}

interface ScientificStudy extends TeamObject {
	group_size: NumberAttribute;
	age_range: NumberAttribute;
	median_age: NumberAttribute;
	treatment_method: StringAttribute;
	treatment_efficacy: StringAttribute;
	complications: StringAttribute;
}

interface DrugWarning extends TeamObject {
	issued_at: DateAttribute;
	injury_risks: string[];
}
