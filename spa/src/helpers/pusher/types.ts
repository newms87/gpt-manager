import { ActionTargetItem } from "quasar-ui-danx";

/**
 * Channel event listener configuration
 */
export interface ChannelEventListener {
	channel: string;
	events: string[];
	callback: (data: ActionTargetItem) => void;
}

/**
 * Subscription tracking metadata
 */
export interface Subscription {
	id: string;
	resourceType: string;
	events: string[];
	modelIdOrFilter: number | string | true | { filter: object };
	expiresAt?: string;
	cacheKey?: string;
	createdAt: Date;
	_batchedWith?: string; // ID of the batch subscription (if batched)
}

/**
 * Subscription payload for API calls
 */
export interface SubscriptionPayload {
	resource_type: string;
	events: string[];
	model_id_or_filter: number | string | true | { filter: object };
}

/**
 * Batched subscription queue entry
 */
export interface SubscriptionBatch {
	resourceType: string;
	events: string[];
	timer: NodeJS.Timeout | null;
	items: Map<string, {  // key: subscriptionId
		subscriptionId: string;
		modelId: number | string;
	}>;
	resolvers: Array<(success: boolean) => void>;
}
