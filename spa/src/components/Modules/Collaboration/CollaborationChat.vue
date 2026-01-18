<template>
	<div class="flex flex-col h-full overflow-hidden">
		<!-- Message list -->
		<div ref="messageListRef" class="flex-grow overflow-y-auto p-4 scroll-smooth bg-slate-50">
			<ListTransition class="space-y-4">
				<CollaborationMessageCard
					v-for="message in visibleMessages"
					:key="message.id"
					:message="message"
					:readonly="readonly"
					@screenshot-needed="$emit('screenshot-needed', $event)"
				/>
			</ListTransition>
		</div>

		<!-- Input area - sticky at bottom -->
		<div v-if="!readonly" class="border-t border-slate-200 bg-white p-3 flex-shrink-0 shadow-[0_-4px_12px_-4px_rgba(0,0,0,0.08)]">
			<CollaborationFileUpload
				v-if="showFileUpload"
				:accept="fileAccept"
				multiple
				class="mb-3"
				@upload="onFilesSelected"
			/>

			<!-- Selected files preview -->
			<div v-if="selectedFiles.length > 0" class="flex flex-wrap gap-2 mb-3">
				<div
					v-for="(file, index) in selectedFiles"
					:key="index"
					class="relative bg-slate-50 rounded-lg border border-slate-200 p-2 pr-8 transition-all duration-200 hover:border-slate-300 hover:shadow-sm"
				>
					<span class="text-sm text-slate-700">{{ file.name }}</span>
					<ActionButton
						type="trash"
						size="xxs"
						class="absolute top-1 right-1"
						@click="removeFile(index)"
					/>
				</div>
			</div>

			<!-- Message input -->
			<div class="flex flex-col gap-2">
				<div class="flex-grow bg-slate-50 rounded-lg border border-slate-200 overflow-hidden focus-within:border-sky-300 focus-within:ring-2 focus-within:ring-sky-100 transition-all duration-200">
					<MarkdownEditor
						v-model="messageText"
						placeholder="Type a message... (Ctrl+Enter to send)"
						:disable="loading || thread.is_running"
						theme="light"
						min-height="60px"
						max-height="200px"
						class="text-sm"
						@keydown.ctrl.enter.prevent="sendMessage"
					/>
				</div>
				<div class="flex items-center justify-end gap-2">
					<ActionButton
						:icon="AttachIcon"
						color="slate"
						size="sm"
						tooltip="Attach files"
						:disable="loading || thread.is_running"
						class="transition-all duration-200 hover:scale-105"
						@click="showFileUpload = !showFileUpload"
					/>
					<ActionButton
						:icon="SendIcon"
						color="sky-invert"
						size="sm"
						label="Send"
						tooltip="Send message (Ctrl+Enter)"
						:loading="loading || thread.is_running"
						:disable="!canSend"
						class="transition-all duration-200 hover:scale-105"
						@click="sendMessage"
					/>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import CollaborationFileUpload from "@/components/Modules/Collaboration/CollaborationFileUpload.vue";
import CollaborationMessageCard from "@/components/Modules/Collaboration/CollaborationMessageCard.vue";
import { CollaborationThread, SendMessagePayload } from "@/components/Modules/Collaboration/types";
import {
	FaSolidPaperclip as AttachIcon,
	FaSolidPaperPlane as SendIcon
} from "danx-icon";
import { ActionButton, ListTransition, MarkdownEditor } from "quasar-ui-danx";
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from "vue";
import { AgentThreadMessage } from "@/types";

const props = withDefaults(defineProps<{
	thread: CollaborationThread;
	loading?: boolean;
	readonly?: boolean;
	fileAccept?: string;
}>(), {
	loading: false,
	readonly: false,
	fileAccept: "image/*,application/pdf"
});

const emit = defineEmits<{
	"send-message": [payload: SendMessagePayload];
	"screenshot-needed": [requestId: string];
}>();

const messageListRef = ref<HTMLElement | null>(null);
const messageText = ref("");
const selectedFiles = ref<File[]>([]);
const showFileUpload = ref(false);

const canSend = computed(() => {
	return (messageText.value.trim().length > 0 || selectedFiles.value.length > 0) &&
		!props.loading &&
		!props.thread.is_running;
});

/**
 * Check if a message looks like a system-generated prompt
 * System prompts typically start with "Please " and contain template instructions
 */
function isSystemGeneratedPrompt(content: string): boolean {
	if (!content) return false;
	const lowerContent = content.toLowerCase().trim();

	// Check for common system prompt patterns
	const systemPromptPatterns = [
		/^please (generate|create|provide|build)/i,
		/^(generate|create) (a|an|the) .*(template|html|document)/i,
		/template generation request/i,
		/your task is to/i
	];

	return systemPromptPatterns.some(pattern => pattern.test(content));
}

/**
 * Get messages from thread (chat_messages is always populated by the backend)
 */
const threadMessages = computed<AgentThreadMessage[]>(() => {
	return props.thread.chat_messages || [];
});

/**
 * Sort messages by timestamp and filter out system-generated prompts
 * Hides auto-generated prompts that the user didn't write themselves
 */
const visibleMessages = computed<AgentThreadMessage[]>(() => {
	if (!threadMessages.value.length) return [];

	// Sort messages by timestamp (oldest first), with ID as secondary sort key for stable ordering
	const sortedMessages = [...threadMessages.value].sort((a, b) => {
		const dateA = new Date(a.timestamp || a.created_at || 0).getTime();
		const dateB = new Date(b.timestamp || b.created_at || 0).getTime();
		if (dateA !== dateB) {
			return dateA - dateB;
		}
		// When timestamps are equal, sort by ID for stable ordering
		if (a.id < 0 && b.id < 0) {
			// For negative IDs (optimistic): higher value first (-1 before -2)
			return b.id - a.id;
		}
		// For positive IDs: ascending (1, 2, 3)
		return a.id - b.id;
	});

	// Filter out system prompts and system-generated prompts
	return sortedMessages.filter((msg) => {
		// Filter out messages marked as system prompts (from backend)
		if (msg.data?.is_system_prompt === true) {
			return false;
		}

		// Only filter user messages further - assistant messages pass through
		if (msg.role !== "user") return true;

		// Hide messages that look like system-generated prompts
		if (isSystemGeneratedPrompt(msg.content)) {
			return false;
		}

		return true;
	});
});

/**
 * Handle paste events for clipboard images
 */
function onPaste(event: ClipboardEvent) {
	if (props.readonly || props.loading || props.thread.is_running) return;

	const items = event.clipboardData?.items;
	if (!items) return;

	const imageFiles: File[] = [];

	for (const item of Array.from(items)) {
		if (item.type.startsWith("image/")) {
			const file = item.getAsFile();
			if (file) {
				// Create a named file for better UX
				const timestamp = new Date().toISOString().replace(/[:.]/g, "-");
				const extension = file.type.split("/")[1] || "png";
				const namedFile = new File([file], `pasted-image-${timestamp}.${extension}`, {
					type: file.type
				});
				imageFiles.push(namedFile);
			}
		}
	}

	if (imageFiles.length > 0) {
		event.preventDefault();
		selectedFiles.value = [...selectedFiles.value, ...imageFiles];
	}
}

// Lifecycle hooks for paste listener
onMounted(() => {
	document.addEventListener("paste", onPaste);
});

onUnmounted(() => {
	document.removeEventListener("paste", onPaste);
});

/**
 * Handle files selected from file upload
 */
function onFilesSelected(files: File[]) {
	selectedFiles.value = [...selectedFiles.value, ...files];
	showFileUpload.value = false;
}

/**
 * Remove a file from the selection
 */
function removeFile(index: number) {
	selectedFiles.value.splice(index, 1);
}

/**
 * Send the message with any attached files
 */
function sendMessage() {
	if (!canSend.value) return;

	const payload: SendMessagePayload = {
		message: messageText.value.trim(),
		files: selectedFiles.value.length > 0 ? selectedFiles.value : undefined
	};

	emit("send-message", payload);

	// Reset form
	messageText.value = "";
	selectedFiles.value = [];
	showFileUpload.value = false;
}

/**
 * Auto-scroll to bottom when new messages arrive
 */
function scrollToBottom() {
	nextTick(() => {
		if (messageListRef.value) {
			messageListRef.value.scrollTop = messageListRef.value.scrollHeight;
		}
	});
}

// Watch for new messages and scroll to bottom
watch(
	() => threadMessages.value.length,
	() => {
		scrollToBottom();
	}
);

// Scroll to bottom on initial load
watch(
	() => messageListRef.value,
	(el) => {
		if (el) {
			scrollToBottom();
		}
	},
	{ immediate: true }
);
</script>
