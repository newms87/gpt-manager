import { ref, computed, watch, type Ref } from "vue";
import { getItem, setItem } from "quasar-ui-danx";
import type { QueuedMessage } from "../types";

/**
 * Composable for managing a local message queue for collaboration threads.
 * Messages are queued locally while waiting for LLM responses, persisted to localStorage.
 */
export function useMessageQueue(threadId: Ref<number | null>) {
	const queuedMessages = ref<QueuedMessage[]>([]);
	let nextTempId = -1;

	const storageKey = computed(() => threadId.value ? `collab-queue-${threadId.value}` : null);

	const hasQueuedMessages = computed(() => queuedMessages.value.length > 0);

	/**
	 * Load queued messages from localStorage
	 */
	function loadFromStorage() {
		if (!storageKey.value) return;

		const stored = getItem(storageKey.value);
		if (stored?.messages) {
			queuedMessages.value = stored.messages;
			// Update nextTempId based on loaded messages to avoid ID collisions
			if (stored.messages.length > 0) {
				const minId = Math.min(...stored.messages.map((m: QueuedMessage) => m.id));
				nextTempId = minId - 1;
			}
		}
	}

	/**
	 * Save queued messages to localStorage
	 */
	function saveToStorage() {
		if (!storageKey.value) return;

		if (queuedMessages.value.length === 0) {
			// Remove key if empty
			localStorage.removeItem(storageKey.value);
		} else {
			setItem(storageKey.value, { messages: queuedMessages.value });
		}
	}

	/**
	 * Generate the next temporary ID (negative to distinguish from server IDs)
	 */
	function getNextTempId(): number {
		return nextTempId--;
	}

	/**
	 * Add a message to the queue
	 */
	function addToQueue(content: string, fileIds?: number[]): QueuedMessage {
		const message: QueuedMessage = {
			id: getNextTempId(),
			content,
			timestamp: new Date().toISOString(),
			fileIds
		};
		queuedMessages.value.push(message);
		return message;
	}

	/**
	 * Clear all queued messages and localStorage
	 */
	function clearQueue() {
		queuedMessages.value = [];
		nextTempId = -1;
		if (storageKey.value) {
			localStorage.removeItem(storageKey.value);
		}
	}

	// Persist changes to localStorage
	watch(queuedMessages, saveToStorage, { deep: true });

	// Clear and reload queue when thread changes
	watch(threadId, (newId, oldId) => {
		if (newId !== oldId) {
			// Reset state for new thread
			queuedMessages.value = [];
			nextTempId = -1;
			loadFromStorage();
		}
	});

	// Initial load
	loadFromStorage();

	return {
		queuedMessages,
		addToQueue,
		clearQueue,
		hasQueuedMessages,
		getNextTempId
	};
}
