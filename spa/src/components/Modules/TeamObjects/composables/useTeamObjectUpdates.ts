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
	
	// Track active subscriptions (now tracked by object ID only, no callback needed)
	const activeSubscriptions = ref<Set<number>>(new Set());

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
	async function subscribeToTeamObjectUpdates(teamObject: TeamObject): Promise<void> {
		if (!pusher || !teamObject?.id || activeSubscriptions.value.has(teamObject.id)) {
			return;
		}

		try {
			// Subscribe to updates for this specific object using new subscription system
			await pusher.subscribeToModel("TeamObject", ["updated"], teamObject.id);

			// Track this subscription
			activeSubscriptions.value.add(teamObject.id);

			// Set up event handler for updates
			pusher.onEvent("TeamObject", "updated", async (eventData: TeamObjectEvent) => {
				if (eventData.id === teamObject.id) {
					// Extract the root object ID (fallback to the event ID if not present)
					const rootObjectId = eventData.root_object_id || eventData.id;

					// Load the root TeamObject with full relationships using the queuing system
					await loadTeamObjectWithQueue(rootObjectId);
				}
			});
		} catch (error) {
			console.error("Failed to subscribe to team object updates:", error);
		}
	}

	/**
	 * Unsubscribe from updates for a TeamObject
	 */
	async function unsubscribeFromTeamObjectUpdates(teamObject?: TeamObject): Promise<void> {
		if (!pusher || !teamObject?.id || !activeSubscriptions.value.has(teamObject.id)) {
			return;
		}

		try {
			// Unsubscribe from the model using new subscription system
			await pusher.unsubscribeFromModel("TeamObject", ["updated"], teamObject.id);

			// Remove from active subscriptions
			activeSubscriptions.value.delete(teamObject.id);
		} catch (error) {
			console.error("Failed to unsubscribe from team object updates:", error);
			// Remove from tracking even if API call fails
			activeSubscriptions.value.delete(teamObject.id);
		}
	}

	/**
	 * Unsubscribe from all active subscriptions
	 */
	async function unsubscribeFromAllUpdates(): Promise<void> {
		if (pusher) {
			// Unsubscribe from each active subscription
			const unsubscribePromises = Array.from(activeSubscriptions.value).map(async (objectId) => {
				try {
					await pusher.unsubscribeFromModel("TeamObject", ["updated"], objectId);
				} catch (error) {
					console.error(`Failed to unsubscribe from team object ${objectId}:`, error);
				}
			});

			await Promise.all(unsubscribePromises);
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