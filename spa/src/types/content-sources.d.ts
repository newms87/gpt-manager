import { ActionTargetItem } from "quasar-ui-danx/types";

export interface ContentSource extends ActionTargetItem {
	id: string;
	name: string;
	type: string;
	url: string;
	config: object;
	per_page: number;
	polled_at: string;
	polling_interval: number;
	workflow_inputs_count: number;
	created_at: string;
}
