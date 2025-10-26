export interface PusherEvent {
	timestamp: Date;
	resourceType: string;
	eventName: string;
	modelId?: number;
	payload: any;
}
