export interface Company {
	id: number;
	name: string;
	url: string;
	logo: string;
	annual_revenue: number;
	operating_income: number;
	net_income: number;
	total_assets: number;
	total_equity: number;
}

export interface Drug {
	id: number;
	name: string;
	url: string;
	logo: string;
	patent_number: string;
	patent_filed_date: string;
	patent_expiration_date: string;
	patent_issued_date: string;
	patent_details: string;
	number_of_users: number;
	market_share: number;
	generic_name: string;
	generics: string[];
	statute_of_limitations_tolling: string;
}

export interface Issue {
	id: number;
	name: string;
	description: string;
	evaluation_score: number;
	severity_level: string;
	hospitalization: boolean;
	surgical_procedure: boolean;
	permanent_disability: boolean;
	death: boolean;
	ongoing_care: boolean;
	economic_damage_min: number;
	economic_damage_max: number;
}

export interface ScientificStudy {
	id: number;
	name: string;
	url: string;
	description: string;
	quality_grade: string;
	injury: string;
	injury_description: string;
	group_size?: number | string;
	age_range?: string;
	median_age?: string;
	treatment_method?: string;
	treatment_efficacy?: string;
	complications?: string;
}

export interface DrugWarning {
	id: number;
	name: string;
	url: string;
	description: string;
	issued_at: string;
	injury_risks: string[];
}

export interface DataSource {
	id: number;
	name: string;
	url: string;
	table: string;
	field: string;
	explanation: string;
}

export interface DrugIssue {
	company: Company;
	drug: Drug;
	issue: Issue;
	scientific_studies: ScientificStudy[];
	warnings: DrugWarning[];
	data_sources: DataSource[];
}
