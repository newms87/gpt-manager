import { WorkflowRun } from "@/types/workflows";

export interface SearchItem {
	product: string;
	injury: string;
	company: string;
	description: string;
	sources: { url: string }[];
}

export interface SearchResult {
	success?: boolean;
	message?: string;
	results: SearchItem[];
}

export interface ResearchResult {
	success?: boolean;
	message?: string;
	workflowRun?: WorkflowRun;
}
