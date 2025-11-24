import md5 from "js-md5";

/**
 * Generate a UUID for subscription tracking
 */
export function generateSubscriptionId(): string {
	return crypto.randomUUID();
}

/**
 * Recursively sort object keys for consistent hashing
 */
export function sortObjectKeys(obj: any): any {
	if (Array.isArray(obj)) {
		return obj.map(sortObjectKeys);
	} else if (obj !== null && typeof obj === "object") {
		return Object.keys(obj)
			.sort()
			.reduce((result, key) => {
				result[key] = sortObjectKeys(obj[key]);
				return result;
			}, {} as any);
	}
	return obj;
}

/**
 * Generate MD5 hash of filter object (MUST match backend implementation)
 */
export function hashFilter(filter: object): string {
	const sorted = sortObjectKeys(filter);
	const json = JSON.stringify(sorted);
	return md5(json);
}

/**
 * Generate subscription tracking key
 */
export function getSubscriptionKey(resourceType: string, modelIdOrFilter: number | string | true | { filter: object }): string {
	if (modelIdOrFilter === true) {
		return `${resourceType}:all`;
	}
	if (typeof modelIdOrFilter === "number" || typeof modelIdOrFilter === "string") {
		return `${resourceType}:id:${modelIdOrFilter}`;
	}
	// It's a filter object
	const filterObj = (modelIdOrFilter as { filter: object }).filter;
	const hash = hashFilter(filterObj);
	return `${resourceType}:filter:${hash}`;
}

/**
 * Generate batch key for ID-based subscriptions
 */
export function getBatchKey(resourceType: string, events: string[]): string {
	return `${resourceType}:${events.sort().join(',')}`;
}

/**
 * Check if a model ID or filter is ID-based (batchable)
 */
export function isIdBasedSubscription(modelIdOrFilter: number | string | true | { filter: object }): boolean {
	return typeof modelIdOrFilter === 'number' ||
		(typeof modelIdOrFilter === 'string' && modelIdOrFilter !== 'true');
}
