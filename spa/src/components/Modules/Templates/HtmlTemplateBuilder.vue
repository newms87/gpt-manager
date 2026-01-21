<template>
	<div class="flex h-full overflow-hidden">
		<!-- Main collaboration area -->
		<div class="flex-grow h-full overflow-hidden">
			<CollaborationPanel
				v-if="thread"
				:thread="thread"
				:loading="loading"
				@send-message="$emit('send-message', $event)"
				@send-batch="$emit('send-batch', $event)"
				@screenshot-needed="handleScreenshotRequest"
			>
				<template #preview>
					<div ref="previewContainerRef" class="h-full">
						<HtmlTemplatePreview
							ref="previewRef"
							:html="template.html_content || ''"
							:css="template.css_content"
							:variables="previewVariables"
							:building-job-dispatch="template.building_job_dispatch"
							:pending-build-context="template.pending_build_context || []"
							:job-dispatches="template.job_dispatches || []"
							:job-dispatch-count="template.job_dispatch_count"
							:can-view-jobs="canViewJobs"
							:is-loading-job-dispatches="isLoadingJobDispatches"
							@retry-build="handleRetryBuild"
							@load-job-dispatches="emit('load-job-dispatches')"
						/>
					</div>
				</template>
			</CollaborationPanel>

			<!-- No thread state - File upload flow -->
			<div v-else class="flex flex-col items-center justify-center h-full bg-slate-50 p-8">
				<div class="max-w-lg w-full">
					<h2 class="text-xl font-semibold text-slate-800 text-center mb-2">
						Start Template Collaboration
					</h2>
					<p class="text-slate-600 text-center mb-6">
						Describe your template below, or optionally upload PDF/images as reference.
					</p>

					<!-- Prompt textarea -->
					<div class="mb-6">
						<textarea
							v-model="initialPrompt"
							class="w-full h-32 px-4 py-3 border border-slate-300 rounded-lg resize-none focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent text-slate-700 placeholder-slate-400"
							placeholder="e.g., Create an invoice template with company logo, customer info, line items table, and total"
						/>
					</div>

					<div class="mb-4">
						<p class="text-sm text-slate-500 text-center mb-2">Optional: Upload reference files</p>
						<CollaborationFileUpload
							accept="image/*,application/pdf"
							multiple
							@upload="onFilesSelected"
						/>
					</div>

					<!-- Selected files summary -->
					<div v-if="pendingFiles.length > 0" class="mb-6">
						<div class="text-sm text-slate-600 mb-2">
							{{ pendingFiles.length }} file{{ pendingFiles.length > 1 ? 's' : '' }} selected
						</div>
						<div class="flex flex-wrap gap-2">
							<div
								v-for="(file, index) in pendingFiles"
								:key="index"
								class="flex items-center gap-2 bg-slate-100 rounded px-3 py-1.5 text-sm text-slate-700"
							>
								<span class="truncate max-w-[150px]">{{ file.name }}</span>
								<ActionButton
									type="cancel"
									size="xxs"
									color="slate"
									@click="removeFile(index)"
								/>
							</div>
						</div>
					</div>

					<div class="flex justify-center mt-6">
						<ActionButton
							type="play"
							label="Begin Collaboration"
							color="sky-invert"
							size="md"
							:disabled="!canStartCollaboration"
							@click="$emit('start-collaboration', pendingFiles, initialPrompt)"
						/>
					</div>
				</div>
			</div>
		</div>

		<!-- Screenshot capture component -->
		<CollaborationScreenshotCapture
			ref="screenshotCaptureRef"
			:target-ref="previewContainerRef"
			:request-id="pendingScreenshotRequestId"
			@captured="onScreenshotCaptured"
			@error="onScreenshotError"
		/>
	</div>
</template>

<script setup lang="ts">
import {
	CollaborationFileUpload,
	CollaborationPanel,
	CollaborationScreenshotCapture,
	QueuedMessage,
	SendMessagePayload
} from "@/components/Modules/Collaboration";
import HtmlTemplatePreview from "@/components/Modules/Templates/HtmlTemplatePreview.vue";
import { authUser } from "@/helpers/auth";
import type { TemplateDefinition, TemplateUpdatePayload } from "@/ui/templates/types";
import { AgentThread } from "@/types";
import { ActionButton } from "quasar-ui-danx";
import { computed, ref } from "vue";

const props = withDefaults(defineProps<{
	template: TemplateDefinition;
	thread?: AgentThread | null;
	loading?: boolean;
	previewVariables?: Record<string, string>;
	isLoadingJobDispatches?: boolean;
}>(), {
	thread: null,
	loading: false,
	previewVariables: () => ({}),
	isLoadingJobDispatches: false
});

const emit = defineEmits<{
	"update-template": [updates: TemplateUpdatePayload];
	"start-collaboration": [files: File[], prompt: string];
	"send-message": [payload: SendMessagePayload];
	"send-batch": [messages: QueuedMessage[]];
	"screenshot-captured": [requestId: string, file: File];
	"retry-build": [];
	"load-job-dispatches": [];
}>();

const previewContainerRef = ref<HTMLElement | null>(null);
const previewRef = ref<InstanceType<typeof HtmlTemplatePreview> | null>(null);
const screenshotCaptureRef = ref<InstanceType<typeof CollaborationScreenshotCapture> | null>(null);
const pendingScreenshotRequestId = ref<string | null>(null);
const pendingFiles = ref<File[]>([]);
const initialPrompt = ref("");

/**
 * Check if collaboration can be started (prompt has text OR files uploaded)
 */
const canStartCollaboration = computed(() => {
	return initialPrompt.value.trim().length > 0 || pendingFiles.value.length > 0;
});

/**
 * Check if the user has permission to view jobs in the UI
 */
const canViewJobs = computed(() => {
	return authUser.value?.can?.viewJobsInUi ?? false;
});

/**
 * Handle files selected via file upload component
 */
function onFilesSelected(files: File[]) {
	pendingFiles.value = [...pendingFiles.value, ...files];
}

/**
 * Remove a file from the pending files list
 */
function removeFile(index: number) {
	pendingFiles.value.splice(index, 1);
}

/**
 * Handle screenshot request from collaboration chat
 */
function handleScreenshotRequest(requestId: string) {
	pendingScreenshotRequestId.value = requestId;
	// Trigger screenshot capture
	if (screenshotCaptureRef.value) {
		screenshotCaptureRef.value.captureScreenshot();
	}
}

/**
 * Handle successful screenshot capture
 */
function onScreenshotCaptured(file: File) {
	if (pendingScreenshotRequestId.value) {
		emit("screenshot-captured", pendingScreenshotRequestId.value, file);
		pendingScreenshotRequestId.value = null;
	}
}

/**
 * Handle screenshot capture error
 */
function onScreenshotError(error: Error) {
	console.error("Screenshot capture failed:", error);
	pendingScreenshotRequestId.value = null;
}

/**
 * Handle retry build request from the preview component
 */
function handleRetryBuild() {
	emit("retry-build");
}

</script>
