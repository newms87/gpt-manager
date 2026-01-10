import { storedFileRoutes } from "@/composables/storedFileRoutes";
import { usePusher } from "@/helpers/pusher";
import { ActionTargetItem, UploadedFile } from "quasar-ui-danx";
import { onUnmounted, ref } from "vue";

export function useStoredFileUpdates() {
	const pusher = usePusher();

	// Track active subscriptions by file ID
	const activeFileSubscriptions = ref<Set<string>>(new Set());

	// Store event listeners for cleanup
	const eventListeners = ref<Map<string, (data: ActionTargetItem) => void>>(new Map());

	/**
	 * Subscribe to real-time updates for a specific StoredFile
	 */
	async function subscribeToFileUpdates(file: UploadedFile): Promise<void> {
		if (!pusher || !file?.id || activeFileSubscriptions.value.has(file.id)) {
			return;
		}

		try {
			// Subscribe to updates for this SPECIFIC file using its ID
			await pusher.subscribeToModel("StoredFile", ["updated"], file.id);

			// Create and store the event listener callback
			const listener = async (data: ActionTargetItem) => {
				if (data.id === file.id) {
					try {
						// routes.details() updates the object in-place via storeObject internally
						await storedFileRoutes.details(file);
					} catch (error) {
						console.error(`Failed to refresh StoredFile ${file.id}:`, error);
					}
				}
			};

			// Register the listener with pusher
			pusher.onModelEvent(file, "updated", listener);

			// Track this subscription and listener
			activeFileSubscriptions.value.add(file.id);
			eventListeners.value.set(file.id, listener);
		} catch (error) {
			console.error("Failed to subscribe to file updates:", error);
		}
	}

	/**
	 * Unsubscribe from updates for a specific StoredFile
	 */
	async function unsubscribeFromFileUpdates(file?: UploadedFile): Promise<void> {
		if (!pusher || !file?.id || !activeFileSubscriptions.value.has(file.id)) {
			return;
		}

		try {
			// Get the stored listener
			const listener = eventListeners.value.get(file.id);

			// Unsubscribe from pusher events
			if (listener) {
				pusher.offModelEvent(file, "updated", listener);
				eventListeners.value.delete(file.id);
			}

			// Unsubscribe from this specific file
			await pusher.unsubscribeFromModel("StoredFile", ["updated"], file.id);

			// Remove from tracking
			activeFileSubscriptions.value.delete(file.id);
		} catch (error) {
			console.error("Failed to unsubscribe from file updates:", error);
			// Clean up tracking even on error
			activeFileSubscriptions.value.delete(file.id);
			eventListeners.value.delete(file.id);
		}
	}

	/**
	 * Unsubscribe from all active file subscriptions
	 */
	async function unsubscribeFromAllFileUpdates(): Promise<void> {
		if (pusher && activeFileSubscriptions.value.size > 0) {
			// Unsubscribe from each file individually
			const unsubscribePromises = Array.from(activeFileSubscriptions.value).map(async (fileId) => {
				try {
					// Note: We can't easily get the file object here, so we just unsubscribe from the model
					await pusher.unsubscribeFromModel("StoredFile", ["updated"], fileId);
				} catch (error) {
					console.error(`Failed to unsubscribe from file ${fileId}:`, error);
				}
			});

			await Promise.all(unsubscribePromises);
		}

		// Clear all tracking
		activeFileSubscriptions.value.clear();
		eventListeners.value.clear();
	}

	// Cleanup on unmount
	onUnmounted(() => {
		unsubscribeFromAllFileUpdates();
	});

	return {
		subscribeToFileUpdates,
		unsubscribeFromFileUpdates,
		unsubscribeFromAllFileUpdates,
		activeFileSubscriptions: activeFileSubscriptions.value
	};
}
