export interface PusherEvent {
	timestamp: Date;
	resourceType: string;
	eventName: string;
	modelId?: number | string;
	payload: any;
	matchingSubscriptions?: string[]; // Subscription IDs that matched this event
}

export interface SubscriptionStatus {
	id: string;
	resourceType: string;
	scope: string;
	events: string[];
	expiresAt: string;
	timeRemaining: number;
	cacheKey?: string;
	createdAt?: Date; // When the subscription was created
}

export interface KeepaliveState {
	lastKeepaliveAt: Date | null;
	nextKeepaliveAt: Date | null;
	keepaliveCount: number;
	lastKeepaliveSuccess: boolean | null;
	lastKeepaliveError: string | null;
}
