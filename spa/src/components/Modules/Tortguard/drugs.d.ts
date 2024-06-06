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
	patient_usage: string;
	patent_number: string;
	patent_filed_date: string;
	patent_expiration_date: string;
	patent_issued_date: string;
	patent_details: string;
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
	description: string;
	quality_grade: string;
	injury: string;
	group_size: number;
}

export interface FDAWarning {
	id: number;
	name: string;
	description: string;
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
	fda_warnings: FDAWarning[];
	data_sources: DataSource[];
}
