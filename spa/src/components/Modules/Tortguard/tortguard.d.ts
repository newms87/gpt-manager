import { ThreadMessage, WorkflowRun } from "@/types";
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

interface DrugSideEffect extends TeamObject {
	evaluation_score?: NumberAttribute;
	severity_level?: NumberAttribute;
	hospitalization?: BooleanAttribute;
	surgical_procedure?: BooleanAttribute;
	permanent_disability?: BooleanAttribute;
	death?: BooleanAttribute;
	ongoing_care?: BooleanAttribute;
	economic_damage_min?: NumberAttribute;
	economic_damage_max?: NumberAttribute;
	quality_of_life?: StringAttribute;
	psychological_impact?: StringAttribute;
	social_impact?: StringAttribute;
	surgical_procedure_description?: StringAttribute;
	surgical_procedure_rate?: NumberAttribute;
	demographics?: StringAttribute;
	economic_impact?: StringAttribute;
	economic_long_term?: StringAttribute;
	indirect_costs?: StringAttribute;
	ongoing_care_duration?: StringAttribute;
	ongoing_care_type?: StringAttribute;
	acute_or_chronic?: StringAttribute;
	duration?: StringAttribute;
	onset?: StringAttribute;
	death_factors?: StringAttribute;
	mortality_rate?: StringAttribute;
	common_disabilities?: StringAttribute;
	prevalence?: StringAttribute;
	hospitalization_rate?: StringAttribute;
	is_reversible?: BooleanAttribute;
	recovery_time?: StringAttribute;
	disability_rate?: StringAttribute;
	hospital_duration?: StringAttribute;
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
	companies: Company[];
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
