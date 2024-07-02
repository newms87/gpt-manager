import { ActionTargetItem } from "quasar-ui-danx";

export interface ContentSource extends ActionTargetItem {
	id: string;
	name: string;
	type: string;
	url: string;
	config: object;
	last_checkpoint: string;
	polling_interval: number;
	workflow_inputs_count: number;
	fetched_at: string;
	created_at: string;
}
