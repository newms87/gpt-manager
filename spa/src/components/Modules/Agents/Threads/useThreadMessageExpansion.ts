import { computed, Ref, ref, watch } from "vue";
import { AgentThreadMessage } from "@/types";
import { getItem, setItem } from "quasar-ui-danx";

export function useThreadMessageExpansion(messages: Ref<AgentThreadMessage[] | undefined>) {
	const expandedMessages = ref<Set<number>>(new Set());
	const expandedFiles = ref<Set<number>>(new Set());

	// Read user preferences from localStorage
	const defaultMessagesExpanded = getItem("thread-messages-default-expanded") ?? false;
	const defaultFilesExpanded = getItem("thread-files-default-expanded") ?? false;

	// Initialize based on user preference
	watch(messages, (msgs) => {
		if (msgs && msgs.length > 0) {
			if (defaultMessagesExpanded) {
				// Expand all messages
				expandedMessages.value = new Set(msgs.map(m => m.id));
			} else {
				// Expand only last message
				const lastMessage = msgs[msgs.length - 1];
				expandedMessages.value = new Set([lastMessage.id]);
			}

			// Handle files
			if (defaultFilesExpanded) {
				// Expand files for all messages that have files
				const messagesWithFiles = msgs.filter(m => m.files && m.files.length > 0);
				expandedFiles.value = new Set(messagesWithFiles.map(m => m.id));
			} else {
				// Collapse all files
				expandedFiles.value = new Set();
			}
		}
	}, { immediate: true });

	function collapseAllMessages() {
		// Keep only last message expanded
		const msgs = messages.value;
		if (msgs && msgs.length > 0) {
			const lastId = msgs[msgs.length - 1].id;
			expandedMessages.value = new Set([lastId]);
		} else {
			expandedMessages.value = new Set();
		}
	}

	function expandAllMessages() {
		// Expand all messages
		const msgs = messages.value;
		if (msgs) {
			expandedMessages.value = new Set(msgs.map(m => m.id));
		}
	}

	function collapseAllFiles() {
		// Collapse all files
		expandedFiles.value = new Set();
	}

	function expandAllFiles() {
		// Expand files for all messages that have files
		const msgs = messages.value;
		if (msgs) {
			const messagesWithFiles = msgs.filter(m => m.files && m.files.length > 0);
			expandedFiles.value = new Set(messagesWithFiles.map(m => m.id));
		}
	}

	function updateMessageExpanded(messageId: number, expanded: boolean) {
		if (expanded) {
			expandedMessages.value.add(messageId);
		} else {
			expandedMessages.value.delete(messageId);
		}
		// Trigger reactivity
		expandedMessages.value = new Set(expandedMessages.value);
	}

	function updateFilesExpanded(messageId: number, expanded: boolean) {
		if (expanded) {
			expandedFiles.value.add(messageId);
		} else {
			expandedFiles.value.delete(messageId);
		}
		// Trigger reactivity
		expandedFiles.value = new Set(expandedFiles.value);
	}

	// Computed properties to check if all are expanded
	const allMessagesExpanded = computed(() => {
		if (!messages.value?.length) return false;
		return messages.value.every(m => expandedMessages.value.has(m.id));
	});

	const allFilesExpanded = computed(() => {
		const withFiles = messages.value?.filter(m => m.files && m.files.length > 0) || [];
		if (!withFiles.length) return false;
		return withFiles.every(m => expandedFiles.value.has(m.id));
	});

	// Toggle functions that save to localStorage
	function toggleAllMessages() {
		if (allMessagesExpanded.value) {
			collapseAllMessages();
			setItem("thread-messages-default-expanded", false);
		} else {
			expandAllMessages();
			setItem("thread-messages-default-expanded", true);
		}
	}

	function toggleAllFiles() {
		if (allFilesExpanded.value) {
			collapseAllFiles();
			setItem("thread-files-default-expanded", false);
		} else {
			expandAllFiles();
			setItem("thread-files-default-expanded", true);
		}
	}

	return {
		expandedMessages,
		expandedFiles,
		collapseAllMessages,
		expandAllMessages,
		collapseAllFiles,
		expandAllFiles,
		updateMessageExpanded,
		updateFilesExpanded,
		allMessagesExpanded,
		allFilesExpanded,
		toggleAllMessages,
		toggleAllFiles
	};
}
