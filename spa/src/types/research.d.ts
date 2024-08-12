import { WorkflowRun } from "@/types/workflows";

export interface SearchResultItem {
	product_name: string;
	product_url: string;
	description: string;
	side_effects: string[];
	treatment_for: string[];
	generic_drug_names: string[];
	companies: SearchResultItemCompany[];
}

export interface SearchResultItemBySideEffect {
	product_name: string;
	product_url: string;
	description: string;
	side_effect: string;
	treatment_for: string[];
	generic_drug_names: string[];
	companies: SearchResultItemCompany[];
}

export interface SearchResultItemCompany {
	name: string;
	parent_name?: string;
}

export interface SearchResult {
	success?: boolean;
	message?: string;
	results: SearchResultItem[];
}

export interface ResearchResult {
	success?: boolean;
	message?: string;
	workflowRun?: WorkflowRun;
}
