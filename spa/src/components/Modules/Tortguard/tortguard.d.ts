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
	workflowRuns?: WorkflowRun[];
}

interface DrugProduct extends TeamObject {
	number_of_users: StringAttribute;
	market_share: StringAttribute;
	annual_revenue: StringAttribute;
	price_per_unit: StringAttribute;

	// The number of years the statute of limitations is tolled for this drug
	statute_of_limitations_tolling: StringAttribute;
	patents: Patent[];
	genericNames: DrugGenericName[];
	// DrugProducts can have multiple DrugGenerics (ie: Actos can be pioglitazone and metformin)
	generics: DrugGeneric[];
	companies: Company[];
	indications: DrugIndication[];
	scientificStudies: ScientificStudy[];
	warnings: DrugWarning[];
}

interface DrugIndication extends TeamObject {}

interface DrugGenericName extends TeamObject {}


interface DrugGeneric extends TeamObject {
	number_of_users: StringAttribute;
	market_share: StringAttribute;
	annual_revenue: StringAttribute;
	price_per_unit: StringAttribute;
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
	priority_date: DateAttribute;
	inventors: StringArrayAttribute;
	owners: StringArrayAttribute;
	specification: StringAttribute;
	claims: StringArrayAttribute;
	legal_status: StringAttribute;
}

interface ScientificStudy extends TeamObject {
	authors: StringArrayAttribute;
	publication_date: DateAttribute;
	journal_name: StringAttribute;
	doi: StringAttribute;
	objective: StringAttribute;
	study_design: StringAttribute;
	methodology: StringAttribute;
	population: StringAttribute;
	sample_size: StringAttribute;
	interventions: StringAttribute;
	control_group: StringAttribute;
	outcomes: StringAttribute;
	results: StringAttribute;
	statistical_analysis: StringAttribute;
	adverse_events: StringAttribute;
	conclusions: StringAttribute;
	limitations: StringAttribute;
	funding_sources: StringAttribute;
	conflicts_of_interest: StringAttribute;
	ethical_approval: StringAttribute;
	trial_registration_number: StringAttribute;
}

interface DrugWarning extends TeamObject {
	reference_number: StringAttribute;
	warning_type: StringAttribute;
	warning_date: DateAttribute;
	reason: StringAttribute;
	affected_populations: StringAttribute;
	recommended_actions: StringAttribute;
	adverse_events: StringAttribute;
	risk_level: StringAttribute;
	regulatory_actions: StringAttribute;
}
