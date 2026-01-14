<template>
	<div class="h-full flex flex-col overflow-hidden bg-slate-100">
		<!-- Header -->
		<div class="flex items-center px-6 py-3 bg-white border-b border-slate-200 shadow-sm flex-shrink-0">
			<ActionButton
				:icon="BackIcon"
				color="slate"
				size="sm"
				tooltip="Back to templates"
				@click="goBack"
			/>
			<div class="ml-3 flex-grow">
				<div class="text-slate-500 text-xs uppercase tracking-wide">HTML Template Builder</div>
				<EditableDiv
					v-if="template"
					:model-value="template.name"
					class="text-lg font-semibold text-slate-800"
					@update:model-value="updateTemplateName"
				/>
				<h1 v-else class="text-lg font-semibold text-slate-800">Loading...</h1>
			</div>
			<div class="flex items-center gap-3">
				<SaveStateIndicator :saving="isSaving" :saved="isSaved" />
				<ActionButton
					v-if="templateHistory.length > 0"
					:icon="HistoryIcon"
					color="slate"
					size="sm"
					:label="`${templateHistory.length}`"
					tooltip="Version History"
					@click="showVersionHistory = true"
				/>
			</div>
		</div>

		<!-- Loading state -->
		<div v-if="isLoading" class="flex-grow flex items-center justify-center">
			<QSpinner color="sky" size="lg" />
		</div>

		<!-- Error state -->
		<div v-else-if="error" class="flex-grow flex items-center justify-center">
			<div class="text-center">
				<ErrorIcon class="w-12 h-12 text-red-500 mx-auto mb-4" />
				<p class="text-slate-600">{{ error }}</p>
				<ActionButton
					type="refresh"
					label="Retry"
					color="sky"
					class="mt-4"
					@click="loadTemplate"
				/>
			</div>
		</div>

		<!-- Template builder -->
		<div v-else-if="template" class="flex-grow overflow-hidden">
			<HtmlTemplateBuilder
				:template="templateForBuilder"
				:thread="collaborationThread"
				:loading="isCollaborationLoading"
				:preview-variables="previewVariables"
				:is-loading-job-dispatches="isLoadingJobDispatches"
				class="h-full"
				@start-collaboration="startCollaboration"
				@send-message="sendMessage"
				@screenshot-captured="uploadScreenshot"
				@retry-build="handleRetryBuild"
				@load-job-dispatches="loadJobDispatches"
			/>
		</div>

		<!-- Version History Modal -->
		<ConfirmDialog
			v-if="showVersionHistory"
			title="Version History"
			content-class="w-[600px] max-w-[90vw]"
			:show-confirm="false"
			cancel-text="Close"
			@close="showVersionHistory = false"
		>
			<CollaborationVersionHistory
				:history="templateHistory"
				:current-version="template?.updated_at"
				@preview="onPreviewVersion"
				@restore="onRestoreVersion"
			/>
		</ConfirmDialog>
	</div>
</template>

<script setup lang="ts">
import { CollaborationVersionHistory, SendMessagePayload } from "@/components/Modules/Collaboration";
import { HtmlTemplateBuilder } from "@/components/Modules/Templates";
import { AgentThread } from "@/types";
import {
	FaSolidArrowLeft as BackIcon,
	FaSolidClockRotateLeft as HistoryIcon,
	FaSolidTriangleExclamation as ErrorIcon
} from "danx-icon";
import { QSpinner } from "quasar";
import { ActionButton, ConfirmDialog, EditableDiv, SaveStateIndicator } from "quasar-ui-danx";
import { computed, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { dxTemplateDefinition } from "../config";
import { useTemplateCollaboration } from "../composables/useTemplateCollaboration";
import type { TemplateDefinition, TemplateDefinitionHistory } from "../types";

const route = useRoute();
const router = useRouter();

const template = ref<TemplateDefinition | null>(null);
const isLoading = ref(true);
const isSaving = ref(false);
const isSaved = ref(true);
const error = ref<string | null>(null);

const collaborationThread = ref<AgentThread | null>(null);
const showVersionHistory = ref(false);

// Lazy loading states
const isLoadingJobDispatches = ref(false);
const isLoadingVariables = ref(false);

// Get the update action for the template name
const updateTemplateAction = dxTemplateDefinition.getAction("update");

// Initialize real-time collaboration subscription
const { subscribeToThread, subscribeToTemplate, subscribeToJobDispatch } = useTemplateCollaboration(template);

// Get actions using the standard pattern
const startCollaborationAction = dxTemplateDefinition.getAction("start-collaboration");
const sendMessageAction = dxTemplateDefinition.getAction("send-message");

// Use action's isApplying for loading state
const isCollaborationLoading = computed(() =>
	startCollaborationAction.isApplying || sendMessageAction.isApplying
);

/**
 * Get template ID from route params
 */
const templateId = computed(() => {
	const id = route.params.id;
	return typeof id === "string" ? parseInt(id, 10) : null;
});

/**
 * Template for the builder component (same type now)
 */
const templateForBuilder = computed<TemplateDefinition | null>(() => {
	return template.value;
});

/**
 * Preview variables for template (sample data)
 */
const previewVariables = computed<Record<string, string>>(() => {
	if (!template.value?.template_variables) return {};

	const vars: Record<string, string> = {};
	template.value.template_variables.forEach(v => {
		if (v.default_value) {
			vars[v.name] = v.default_value;
		}
	});
	return vars;
});

/**
 * Template history sorted by created_at descending
 */
const templateHistory = computed<TemplateDefinitionHistory[]>(() => {
	if (!template.value?.history) return [];
	return [...template.value.history].sort((a, b) =>
		new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
	);
});

/**
 * Update template name
 */
async function updateTemplateName(name: string) {
	if (!template.value || !name.trim()) return;
	isSaving.value = true;
	isSaved.value = false;
	await updateTemplateAction.trigger(template.value, { name: name.trim() });
	isSaving.value = false;
	isSaved.value = true;
}

/**
 * Preview a historical version
 */
function onPreviewVersion(historyId: number) {
	const version = templateHistory.value.find(h => h.id === historyId);
	if (version) {
		console.log("Previewing version:", version);
	}
}

/**
 * Restore a historical version
 */
async function onRestoreVersion(historyId: number) {
	if (!template.value) return;
	showVersionHistory.value = false;

	const restoreAction = dxTemplateDefinition.getAction("restore-version");
	await restoreAction.trigger(template.value, { history_id: historyId });

	// Reload template to get updated content
	await loadTemplate();
}

/**
 * Load template data
 */
async function loadTemplate() {
	if (!templateId.value) {
		error.value = "Invalid template ID";
		isLoading.value = false;
		return;
	}

	isLoading.value = true;
	error.value = null;

	try {
		const response = await dxTemplateDefinition.routes.list({
			filter: { id: templateId.value },
			fields: {
				html_content: true,
				css_content: true,
				history: true,
				collaboration_threads: { messages: true },
				building_job_dispatch: true,
				pending_build_context: true,
				job_dispatch_count: true,
				template_variable_count: true
			}
		});

		if (response.data && response.data.length > 0) {
			template.value = response.data[0];

			// Subscribe to template updates for building status changes
			await subscribeToTemplate(template.value);

			// Set the collaboration thread if one exists and subscribe to updates
			if (template.value.collaboration_threads?.length) {
				collaborationThread.value = template.value.collaboration_threads[0] as AgentThread;
				await subscribeToThread(collaborationThread.value);
			}
		} else {
			error.value = "Template not found";
		}
	} catch (e) {
		console.error("Failed to load template:", e);
		error.value = "Failed to load template";
	} finally {
		isLoading.value = false;
	}
}

/**
 * Lazy load job dispatches when the Jobs tab is first accessed
 */
async function loadJobDispatches() {
	if (!template.value || template.value.job_dispatches || isLoadingJobDispatches.value) return;
	isLoadingJobDispatches.value = true;
	await dxTemplateDefinition.routes.details(template.value, { job_dispatches: true });
	isLoadingJobDispatches.value = false;
}

/**
 * Lazy load template variables when needed
 */
async function loadTemplateVariables() {
	if (!template.value || template.value.template_variables || isLoadingVariables.value) return;
	isLoadingVariables.value = true;
	await dxTemplateDefinition.routes.details(template.value, { template_variables: true });
	isLoadingVariables.value = false;
}

/**
 * Navigate back to templates list
 */
function goBack() {
	router.push({ name: "ui.templates" });
}

/**
 * Start a new collaboration thread with optional files and prompt
 */
async function startCollaboration(files: File[], prompt: string) {
	if (!template.value) return;

	// Note: files need to be uploaded first to get file_ids, but for now just support prompt-only
	const result = await startCollaborationAction.trigger(template.value, {
		prompt,
		file_ids: [] // TODO: Upload files first using FileUpload helper
	});

	// The result.item is the updated template with collaboration_threads
	// storeObject already updated template.value reactively
	// But we need to set the collaborationThread ref from the response and subscribe to updates
	if (result?.item?.collaboration_threads?.length) {
		collaborationThread.value = result.item.collaboration_threads[0] as AgentThread;
		await subscribeToThread(collaborationThread.value);
	}
}

/**
 * Generate a temporary ID for optimistic messages
 */
let optimisticIdCounter = -1;
function getOptimisticId(): number {
	return optimisticIdCounter--;
}

/**
 * Send a message to the collaboration thread with optimistic updates
 * Immediately shows the user message and a thinking indicator
 */
async function sendMessage(payload: SendMessagePayload) {
	if (!template.value || !collaborationThread.value) return;
	isSaving.value = true;
	isSaved.value = false;

	// Create optimistic user message
	const optimisticUserMessage = {
		id: getOptimisticId(),
		role: "user" as const,
		title: "",
		content: payload.message,
		timestamp: new Date().toISOString(),
		data: {}
	};

	// Create optimistic assistant thinking message
	const optimisticAssistantMessage = {
		id: getOptimisticId(),
		role: "assistant" as const,
		title: "",
		content: "",
		timestamp: new Date().toISOString(),
		data: { is_thinking: true }
	};

	// Immediately add optimistic messages to the thread
	collaborationThread.value = {
		...collaborationThread.value,
		messages: [
			...collaborationThread.value.messages,
			optimisticUserMessage,
			optimisticAssistantMessage
		],
		is_running: true
	};

	const result = await sendMessageAction.trigger(template.value, {
		thread_id: collaborationThread.value.id,
		message: payload.message,
		file_id: null // TODO: Handle file attachments
	});

	// The result.item is the updated template with collaboration_threads
	// This replaces the optimistic messages with real ones
	if (result?.item?.collaboration_threads?.length) {
		collaborationThread.value = result.item.collaboration_threads[0] as AgentThread;
	}
	isSaving.value = false;
	isSaved.value = true;
}

/**
 * Upload a screenshot in response to a request
 */
async function uploadScreenshot(requestId: string, file: File) {
	if (!template.value || !collaborationThread.value) return;

	// TODO: Upload file first using FileUpload helper to get file_id
	// For now, this will need to be handled differently
	console.warn("Screenshot upload not yet implemented with new pattern");
}

/**
 * Handle retry build request from the preview component
 * Clears the failed build lock and triggers a new build
 */
async function handleRetryBuild() {
	if (!template.value) return;

	// TODO: Call backend to clear the failed build and trigger a new one
	// For now, just log the request
	console.log("Retry build requested for template:", template.value.id);
}

// Watch for route changes
watch(templateId, (newId) => {
	if (newId) {
		loadTemplate();
	}
});

// Watch for building job dispatch changes to subscribe/unsubscribe
watch(
	() => template.value?.building_job_dispatch_id,
	async (newId, oldId) => {
		if (newId && newId !== oldId) {
			await subscribeToJobDispatch(newId);
		}
	},
	{ immediate: true }
);

onMounted(() => {
	loadTemplate();
});
</script>
