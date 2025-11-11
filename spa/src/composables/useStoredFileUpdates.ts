import { usePusher } from "@/helpers/pusher";
import { UploadedFile } from "quasar-ui-danx";
import { onUnmounted, ref } from "vue";

export function useStoredFileUpdates() {
	const pusher = usePusher();

	// Track active subscriptions by file ID
	const activeFileSubscriptions = ref<Set<string>>(new Set());

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

			// Track this subscription
			activeFileSubscriptions.value.add(file.id);
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
			// Unsubscribe from this specific file
			await pusher.unsubscribeFromModel("StoredFile", ["updated"], file.id);

			// Remove from tracking
			activeFileSubscriptions.value.delete(file.id);
		} catch (error) {
			console.error("Failed to unsubscribe from file updates:", error);
			activeFileSubscriptions.value.delete(file.id);
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
					await pusher.unsubscribeFromModel("StoredFile", ["updated"], fileId);
				} catch (error) {
					console.error(`Failed to unsubscribe from file ${fileId}:`, error);
				}
			});

			await Promise.all(unsubscribePromises);
		}

		activeFileSubscriptions.value.clear();
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
