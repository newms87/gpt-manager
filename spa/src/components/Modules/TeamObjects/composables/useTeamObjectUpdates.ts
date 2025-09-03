import type { TeamObject } from "@/components/Modules/TeamObjects/team-objects";
import { dxTeamObject } from "@/components/Modules/TeamObjects/config";
import { usePusher } from "@/helpers/pusher";
import { onUnmounted, ref } from "vue";

interface QueuedRequest {
	resolve: (value: TeamObject) => void;
	reject: (error: any) => void;
}

interface TeamObjectEvent {
	id: number;
	root_object_id?: number;
	schema_definition_id: number;
	updated_at: string;
	__type: "TeamObjectEvent";
}

export function useTeamObjectUpdates() {
	// Initialize pusher
	const pusher = usePusher();
	
	// Track which objects are currently being loaded to prevent duplicate requests
	const loadingObjects = ref<Set<number>>(new Set());
	
	// Queue for requests that come in while an object is already loading
	const requestQueues = ref<Map<number, QueuedRequest[]>>(new Map());
	
	// Track active subscriptions with their callbacks for cleanup
	const activeSubscriptions = ref<Map<number, (data: TeamObjectEvent) => Promise<void>>>(new Map());

	/**
	 * Load a TeamObject with full relationships, with request queuing to prevent duplicates
	 */
	async function loadTeamObjectWithQueue(teamObjectOrId: TeamObject | number): Promise<TeamObject> {
		const objectId = typeof teamObjectOrId === 'number' ? teamObjectOrId : teamObjectOrId.id;

		// If already loading, queue this request
		if (loadingObjects.value.has(objectId)) {
			return new Promise<TeamObject>((resolve, reject) => {
				if (!requestQueues.value.has(objectId)) {
					requestQueues.value.set(objectId, []);
				}
				requestQueues.value.get(objectId)!.push({ resolve, reject });
			});
		}

		// Mark as loading
		loadingObjects.value.add(objectId);

		try {
			// Create a minimal TeamObject for loading if we only have an ID
			const teamObject = typeof teamObjectOrId === 'number' 
				? { id: objectId, __type: "TeamObjectResource" } as TeamObject
				: teamObjectOrId;

			// Load the object with full relationships
			await dxTeamObject.routes.details(teamObject, {
				attributes: true,
				relations: true
			});

			// Process any queued requests for this object
			const queue = requestQueues.value.get(objectId);
			if (queue) {
				queue.forEach(({ resolve }) => resolve(teamObject));
				requestQueues.value.delete(objectId);
			}

			return teamObject;
		} catch (error) {
			// Reject any queued requests
			const queue = requestQueues.value.get(objectId);
			if (queue) {
				queue.forEach(({ reject }) => reject(error));
				requestQueues.value.delete(objectId);
			}
			throw error;
		} finally {
			// Always remove from loading set
			loadingObjects.value.delete(objectId);
		}
	}

	/**
	 * Subscribe to real-time updates for a TeamObject
	 */
	function subscribeToTeamObjectUpdates(teamObject: TeamObject) {
		if (!pusher || !teamObject?.id || activeSubscriptions.value.has(teamObject.id)) {
			return;
		}

		// Create the callback function for handling lightweight TeamObjectEvent data
		const updateCallback = async (eventData: TeamObjectEvent) => {
			// Extract the root object ID (fallback to the event ID if not present)
			const rootObjectId = eventData.root_object_id || eventData.id;
			
			// Load the root TeamObject with full relationships using the queuing system
			await loadTeamObjectWithQueue(rootObjectId);
		};

		// Store the callback for later cleanup
		activeSubscriptions.value.set(teamObject.id, updateCallback);

		// Subscribe to updates for this specific object
		pusher.onModelEvent(teamObject, "updated", updateCallback);
	}

	/**
	 * Unsubscribe from updates for a TeamObject
	 */
	function unsubscribeFromTeamObjectUpdates(teamObject?: TeamObject) {
		if (!pusher || !teamObject?.id) {
			return;
		}

		// Get the stored callback
		const callback = activeSubscriptions.value.get(teamObject.id);
		if (callback) {
			// Unsubscribe using the offModelEvent method
			pusher.offModelEvent(teamObject, "updated", callback as any);
			
			// Remove from active subscriptions
			activeSubscriptions.value.delete(teamObject.id);
		}
	}

	/**
	 * Unsubscribe from all active subscriptions
	 */
	function unsubscribeFromAllUpdates() {
		if (pusher) {
			// Unsubscribe from each active subscription
			activeSubscriptions.value.forEach((callback, objectId) => {
				// Create a minimal TeamObject with ID and __type for unsubscribing
				const teamObject = { 
					id: objectId,
					__type: "TeamObjectResource"
				} as TeamObject;
				pusher.offModelEvent(teamObject, "updated", callback as any);
			});
		}
		
		// Clear all tracking
		activeSubscriptions.value.clear();
		// Clean up any pending queues
		requestQueues.value.clear();
		loadingObjects.value.clear();
	}

	// Cleanup on unmount
	onUnmounted(() => {
		unsubscribeFromAllUpdates();
	});

	return {
		subscribeToTeamObjectUpdates,
		unsubscribeFromTeamObjectUpdates,
		unsubscribeFromAllUpdates,
		loadTeamObjectWithQueue,
		// Expose loading state for debugging/UI feedback
		loadingObjects: loadingObjects.value,
		activeSubscriptions: activeSubscriptions.value
	};
}