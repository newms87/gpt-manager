<template>
	<div
		class="overflow-hidden rounded-lg border shadow-sm animate-message-in"
		:class="[avatar.messageClass, isCodeGeneration ? 'ring-1 ring-green-200' : '']"
	>
		<div class="flex items-center px-3 py-2">
			<div class="rounded-full p-1.5 w-7 h-7 flex items-center justify-center shadow-sm" :class="avatar.class">
				<component :is="avatar.icon" class="w-3 text-white" :class="avatar.iconClass" />
			</div>
			<div class="font-semibold text-slate-700 ml-2 flex-grow text-sm">
				{{ roleLabel }}
			</div>
			<div class="text-xs text-slate-400 mr-2 whitespace-nowrap">
				{{ fDateTime(message.timestamp) }}
			</div>
			<ShowHideButton v-model="showContent" :name="'collab-message-' + message.id" />
		</div>

		<template v-if="showContent">
			<QSeparator class="bg-slate-200/60" />
			<div class="text-sm px-3 py-3">
				<!-- Thinking indicator for optimistic messages -->
				<div
					v-if="isThinking"
					class="flex items-center text-slate-500 animate-pulse"
				>
					<QSpinner color="sky" size="sm" class="mr-3" />
					<span class="text-sm">Thinking...</span>
				</div>

				<!-- Screenshot request indicator -->
				<div
					v-else-if="screenshotRequest && screenshotRequest.status === 'pending'"
					class="bg-amber-100 text-amber-800 border border-amber-300 p-3 rounded mb-3 flex items-center transition-all duration-200"
				>
					<CameraIcon class="w-4 mr-2" />
					<span class="flex-grow">Screenshot requested: {{ screenshotRequest.reason || "Preview needed" }}</span>
					<ActionButton
						type="confirm"
						label="Capture"
						color="amber-invert"
						size="sm"
						@click="$emit('screenshot-needed', screenshotRequest.id)"
					/>
				</div>

				<!-- Code generation summary (assistant messages with HTML/CSS) -->
				<div
					v-else-if="isCodeGeneration"
					class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-lg p-3 flex items-center"
				>
					<div class="bg-green-500 rounded-full p-1.5 mr-3 shadow-sm">
						<CheckIcon class="w-3 text-white" />
					</div>
					<span class="text-green-800 font-medium">{{ displayContent }}</span>
				</div>
				<!-- Conversation agent response (message field only) -->
				<MarkdownEditor
					v-else-if="isConversationResponse"
					:model-value="displayContent || ''"
					readonly
					hide-footer
					theme="light"
					editor-class="text-slate-800 bg-slate-50 rounded"
				/>
				<!-- JSON data display (other non-code responses) -->
				<CodeViewer
					v-else-if="isJSON(message.content)"
					:model-value="message.content"
					format="yaml"
					:can-edit="false"
					theme="light"
				/>
				<!-- Markdown/text display -->
				<MarkdownEditor
					v-else-if="!isThinking"
					:model-value="displayContent || ''"
					readonly
					hide-footer
					theme="light"
					editor-class="text-slate-800 bg-slate-50 rounded"
				/>

				<!-- Additional data section -->
				<template v-if="message.data && Object.keys(message.data).length > 0 && !screenshotRequest && !isThinking">
					<div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mt-4 mb-2">Data</div>
					<CodeViewer
						:model-value="message.data"
						format="yaml"
						:can-edit="false"
						theme="light"
						collapsible
					/>
				</template>

				<!-- File attachments -->
				<template v-if="hasFiles">
					<div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mt-4 mb-2">Attachments</div>
					<div class="flex flex-wrap gap-2">
						<FilePreview
							v-for="file in message.files"
							:key="file.id"
							:file="file"
							downloadable
							class="w-24 h-24"
						/>
					</div>
				</template>
			</div>
		</template>
	</div>
</template>

<script setup lang="ts">
import { ScreenshotRequest } from "@/components/Modules/Collaboration/types";
import { AgentThreadMessage } from "@/types";
import {
	FaRegularUser as UserIcon,
	FaSolidCamera as CameraIcon,
	FaSolidCheck as CheckIcon,
	FaSolidRobot as AssistantIcon
} from "danx-icon";
import { QSpinner } from "quasar";
import {
	ActionButton,
	CodeViewer,
	fDateTime,
	FilePreview,
	isJSON,
	MarkdownEditor,
	ShowHideButton
} from "quasar-ui-danx";
import { computed, ref } from "vue";

const props = withDefaults(defineProps<{
	message: AgentThreadMessage;
	readonly?: boolean;
}>(), {
	readonly: false
});

defineEmits<{
	"screenshot-needed": [requestId: string];
}>();

const showContent = ref(true);

const isUserMessage = computed(() => props.message.role === "user");
const hasFiles = computed(() => props.message.files && props.message.files.length > 0);

/**
 * Check if this is an optimistic "thinking" message
 */
const isThinking = computed(() => {
	if (props.message.role !== "assistant") return false;
	return props.message.data?.is_thinking === true;
});

const roleLabel = computed(() => {
	return isUserMessage.value ? "You" : "Assistant";
});

const avatar = computed<{
	icon: object;
	class: string;
	iconClass?: string;
	messageClass?: string;
}>(() => {
	if (isUserMessage.value) {
		return { icon: UserIcon, class: "bg-lime-500", messageClass: "bg-white border-lime-200/80" };
	}
	return { icon: AssistantIcon, class: "bg-sky-500", iconClass: "w-4", messageClass: "bg-sky-50/50 border-sky-200/80" };
});

/**
 * Extract screenshot request from message data if present
 */
const screenshotRequest = computed<ScreenshotRequest | null>(() => {
	if (props.message.data && typeof props.message.data === "object") {
		const data = props.message.data as Record<string, unknown>;
		if (data.screenshot_request && typeof data.screenshot_request === "object") {
			return data.screenshot_request as ScreenshotRequest;
		}
	}
	return null;
});

/**
 * Check if this is a code generation message (assistant response with HTML/CSS)
 */
const isCodeGeneration = computed(() => {
	if (props.message.role !== "assistant") return false;
	if (!isJSON(props.message.content)) return false;
	try {
		const json = JSON.parse(props.message.content);
		return !!(json.html_content || json.css_content);
	} catch {
		return false;
	}
});

/**
 * Check if this is a conversation agent response (has 'message' field, no html_content)
 */
const isConversationResponse = computed(() => {
	if (props.message.role !== "assistant") return false;
	if (!isJSON(props.message.content)) return false;
	try {
		const json = JSON.parse(props.message.content);
		// Has message field but is NOT a code generation response
		return json.message && !json.html_content && !json.css_content;
	} catch {
		return false;
	}
});

/**
 * Get the display content for the message
 * For assistant messages with generated code, show commentary only
 * For conversation agent responses, show just the message field
 */
const displayContent = computed(() => {
	if (props.message.role === "user") {
		return props.message.content;
	}

	// Try to parse as JSON (assistant response)
	if (isJSON(props.message.content)) {
		try {
			const json = JSON.parse(props.message.content);

			// Code generation response (html_content/css_content present)
			if (json.html_content || json.css_content) {
				if (json.commentary) return json.commentary;
				if (json.explanation) return json.explanation;
				if (json.message) return json.message;
				// Build a summary of what was updated
				const updates: string[] = [];
				if (json.html_content) updates.push("HTML template");
				if (json.css_content) updates.push("CSS styles");
				if (json.variable_names?.length) updates.push(`${json.variable_names.length} variables`);
				return `Updated: ${updates.join(", ")}`;
			}

			// Conversation agent response (has 'message' field but no html_content)
			if (json.message) {
				return json.message;
			}

			// Other JSON responses, return original content
			return props.message.content;
		} catch {
			return props.message.content;
		}
	}

	return props.message.content;
});
</script>

<style scoped>
.animate-message-in {
	animation: messageSlideIn 0.3s ease-out;
}

@keyframes messageSlideIn {
	from {
		opacity: 0;
		transform: translateY(8px);
	}
	to {
		opacity: 1;
		transform: translateY(0);
	}
}
</style>
