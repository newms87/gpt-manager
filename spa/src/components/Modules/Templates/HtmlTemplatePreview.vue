<template>
	<div ref="containerRef" class="h-full w-full bg-slate-100 rounded-lg overflow-hidden flex flex-col shadow-sm">
		<!-- Tab bar for switching views -->
		<div v-if="html" class="flex border-b border-slate-200 bg-white px-2 flex-shrink-0">
			<button
				:class="[
					'px-4 py-2.5 text-sm font-medium transition-all duration-200 relative',
					activeTab === 'preview'
						? 'text-sky-600'
						: 'text-slate-500 hover:text-slate-700 hover:bg-slate-50'
				]"
				@click="activeTab = 'preview'"
			>
				<EyeIcon class="w-3.5 inline-block mr-1.5 -mt-0.5" />
				Preview
				<span
					v-if="activeTab === 'preview'"
					class="absolute bottom-0 left-2 right-2 h-0.5 bg-sky-500 rounded-t-full"
				/>
			</button>
			<button
				:class="[
					'px-4 py-2.5 text-sm font-medium transition-all duration-200 relative',
					activeTab === 'code'
						? 'text-sky-600'
						: 'text-slate-500 hover:text-slate-700 hover:bg-slate-50'
				]"
				@click="activeTab = 'code'"
			>
				<CodeIcon class="w-3.5 inline-block mr-1.5 -mt-0.5" />
				Code
				<span
					v-if="activeTab === 'code'"
					class="absolute bottom-0 left-2 right-2 h-0.5 bg-sky-500 rounded-t-full"
				/>
			</button>
			<!-- Building tab - only shown when there's an active build or pending builds -->
			<button
				v-if="isBuilding || pendingBuildContext.length > 0"
				:class="[
					'px-4 py-2.5 text-sm font-medium transition-all duration-200 relative',
					activeTab === 'building'
						? 'text-amber-600'
						: 'text-amber-500 hover:text-amber-700 hover:bg-amber-50'
				]"
				@click="activeTab = 'building'"
			>
				<!-- Spinning loader icon -->
				<svg v-if="isBuilding" class="w-3.5 inline-block mr-1.5 -mt-0.5 animate-spin" viewBox="0 0 24 24" fill="none">
					<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
					<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
				</svg>
				Building...
				<span
					v-if="activeTab === 'building'"
					class="absolute bottom-0 left-2 right-2 h-0.5 bg-amber-500 rounded-t-full"
				/>
			</button>
			<!-- Jobs tab - only shown when user has permission and there are job dispatches -->
			<button
				v-if="canViewJobs && jobDispatchCount && jobDispatchCount > 0"
				:class="[
					'px-4 py-2.5 text-sm font-medium transition-all duration-200 relative',
					activeTab === 'jobs'
						? 'text-teal-600'
						: 'text-teal-500 hover:text-teal-700 hover:bg-teal-50'
				]"
				@click="activeTab = 'jobs'"
			>
				<JobsIcon class="w-3.5 inline-block mr-1.5 -mt-0.5" />
				Jobs ({{ jobDispatchCount }})
				<span
					v-if="activeTab === 'jobs'"
					class="absolute bottom-0 left-2 right-2 h-0.5 bg-teal-500 rounded-t-full"
				/>
			</button>
		</div>

		<!-- Content area -->
		<div class="flex-grow overflow-hidden p-4">
			<!-- Empty state when no HTML content -->
			<div v-if="!html" class="flex flex-col items-center justify-center h-full text-slate-400">
				<div class="bg-slate-200/50 rounded-full p-6 mb-6">
					<DocumentIcon class="w-12 h-12 opacity-60" />
				</div>
				<p class="text-lg font-semibold text-slate-500 mb-2">No Template Content Yet</p>
				<p class="text-sm text-center max-w-md text-slate-400 leading-relaxed">
					Send a message in the chat to generate your HTML template.
					The preview will update automatically as the AI creates your template.
				</p>
			</div>

			<!-- Preview tab - Paper/document effect wrapper -->
			<div
				v-else-if="activeTab === 'preview'"
				class="h-full bg-white rounded-lg shadow-lg overflow-hidden ring-1 ring-slate-200/50"
			>
				<iframe
					ref="iframeRef"
					:srcdoc="iframeContent"
					sandbox="allow-scripts"
					class="w-full h-full border-0"
					title="Template Preview"
				/>
			</div>

			<!-- Code tab -->
			<div v-else-if="activeTab === 'code'" class="h-full overflow-auto bg-white rounded-lg shadow-lg ring-1 ring-slate-200/50 p-4">
				<div class="mb-4">
					<div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2 flex items-center">
						<HtmlIcon class="w-3 mr-1.5 text-orange-500" />
						HTML
					</div>
					<CodeViewer
						:model-value="html"
						format="html"
						:can-edit="false"
						theme="light"
					/>
				</div>
				<div v-if="css">
					<div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2 flex items-center">
						<CssIcon class="w-3 mr-1.5 text-blue-500" />
						CSS
					</div>
					<CodeViewer
						:model-value="css"
						format="css"
						:can-edit="false"
						theme="light"
					/>
				</div>
			</div>

			<!-- Building tab -->
			<div v-else-if="activeTab === 'building'" class="h-full overflow-auto bg-white rounded-lg shadow-lg ring-1 ring-slate-200/50 p-6">
				<div class="space-y-4">
					<!-- Status badge -->
					<div class="flex items-center gap-3">
						<span
							:class="[
								'px-3 py-1 rounded-full text-sm font-medium',
								buildingJobDispatch?.status === 'Running' ? 'bg-amber-100 text-amber-800' :
								buildingJobDispatch?.status === 'Complete' ? 'bg-green-100 text-green-800' :
								buildingJobDispatch?.status === 'Exception' || buildingJobDispatch?.status === 'Failed' ? 'bg-red-100 text-red-800' :
								'bg-slate-100 text-slate-600'
							]"
						>
							{{ buildingJobDispatch?.status || "Pending" }}
						</span>
						<span v-if="buildingJobDispatch?.ran_at" class="text-sm text-slate-500">
							Started {{ formatRelativeTime(buildingJobDispatch.ran_at) }}
						</span>
					</div>

					<!-- Elapsed time -->
					<div v-if="buildingJobDispatch?.ran_at && !buildingJobDispatch?.completed_at" class="text-sm text-slate-600">
						<span class="font-medium">Elapsed:</span> {{ elapsedTime }}
					</div>

					<!-- Pending builds indicator -->
					<div v-if="pendingBuildContext.length > 0" class="bg-amber-50 border border-amber-200 rounded-lg p-3">
						<div class="text-sm font-medium text-amber-800">
							{{ pendingBuildContext.length }} additional build request{{ pendingBuildContext.length > 1 ? "s" : "" }} queued
						</div>
						<div class="text-xs text-amber-600 mt-1">
							These will be processed after the current build completes
						</div>
					</div>

					<!-- Error display -->
					<div
						v-if="buildingJobDispatch?.status === 'Exception' || buildingJobDispatch?.status === 'Failed'"
						class="bg-red-50 border border-red-200 rounded-lg p-4"
					>
						<div class="text-sm font-medium text-red-800 mb-2">Build Failed</div>
						<div class="text-sm text-red-600">
							{{ buildingJobDispatch?.data?.error || "An error occurred during the build" }}
						</div>
						<ActionButton
							type="refresh"
							label="Retry Build"
							color="red-invert"
							size="sm"
							class="mt-3"
							@click="emit('retry-build')"
						/>
					</div>
				</div>
			</div>

			<!-- Jobs tab -->
			<div v-else-if="activeTab === 'jobs'" class="h-full overflow-auto bg-white rounded-lg shadow-lg ring-1 ring-slate-200/50 p-6">
				<div v-if="isLoadingJobDispatches" class="flex items-center justify-center h-full">
					<QSpinner color="teal" size="lg" />
				</div>
				<JobDispatchList v-else :jobs="jobDispatches" />
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import JobDispatchList from "@/components/Modules/Audits/JobDispatches/JobDispatchList.vue";
import { provideAuditCardTheme } from "@/composables/useAuditCardTheme";
import type { BuildingJobDispatch } from "@/ui/templates/types";
import {
	FaSolidCode as CodeIcon,
	FaSolidEye as EyeIcon,
	FaSolidFileCode as DocumentIcon,
	FaSolidHashtag as CssIcon,
	FaSolidListCheck as JobsIcon,
	FaSolidTags as HtmlIcon
} from "danx-icon";
import { QSpinner } from "quasar";
import { ActionButton, CodeViewer } from "quasar-ui-danx";
import { computed, onMounted, onUnmounted, ref, watch } from "vue";

// Provide light theme for JobDispatchCard components in the Jobs tab
provideAuditCardTheme(ref("light"));

const props = withDefaults(defineProps<{
	html: string;
	css?: string;
	variables?: Record<string, string>;
	buildingJobDispatch?: BuildingJobDispatch | null;
	pendingBuildContext?: string[];
	jobDispatches?: BuildingJobDispatch[];
	jobDispatchCount?: number | null;
	canViewJobs?: boolean;
	isLoadingJobDispatches?: boolean;
}>(), {
	css: "",
	variables: () => ({}),
	buildingJobDispatch: null,
	pendingBuildContext: () => [],
	jobDispatches: () => [],
	jobDispatchCount: null,
	canViewJobs: false,
	isLoadingJobDispatches: false
});

const emit = defineEmits<{
	(e: "retry-build"): void;
	(e: "load-job-dispatches"): void;
}>();

/**
 * Check if there's an active building job
 */
const isBuilding = computed(() => !!props.buildingJobDispatch);

/**
 * Timer for elapsed time updates
 */
const now = ref(Date.now());
let intervalId: number | null = null;

onMounted(() => {
	intervalId = window.setInterval(() => {
		now.value = Date.now();
	}, 1000);
});

onUnmounted(() => {
	if (intervalId) {
		clearInterval(intervalId);
	}
});

/**
 * Calculate elapsed time since build started
 */
const elapsedTime = computed(() => {
	if (!props.buildingJobDispatch?.ran_at) return "";
	const start = new Date(props.buildingJobDispatch.ran_at).getTime();
	const elapsed = Math.floor((now.value - start) / 1000);
	const minutes = Math.floor(elapsed / 60);
	const seconds = elapsed % 60;
	return `${minutes}m ${seconds}s`;
});

/**
 * Format a date string as relative time
 */
function formatRelativeTime(dateStr: string): string {
	const date = new Date(dateStr);
	const nowDate = new Date();
	const seconds = Math.floor((nowDate.getTime() - date.getTime()) / 1000);

	if (seconds < 60) return "just now";
	if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
	if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
	return date.toLocaleDateString();
}

const containerRef = ref<HTMLElement | null>(null);
const iframeRef = ref<HTMLIFrameElement | null>(null);
const activeTab = ref<"preview" | "code" | "building" | "jobs">("preview");

/**
 * Auto-switch to building tab when build starts, back to preview when complete
 */
watch(() => props.buildingJobDispatch, (newVal, oldVal) => {
	// Switch to building tab when build starts
	if (newVal && !oldVal) {
		activeTab.value = "building";
	}
	// Switch back to preview when build completes
	if (!newVal && oldVal && oldVal.status === "Running") {
		activeTab.value = "preview";
	}
});

/**
 * Lazy load job dispatches when Jobs tab is clicked for the first time
 */
watch(activeTab, (tab) => {
	if (tab === "jobs" && !props.jobDispatches?.length && !props.isLoadingJobDispatches) {
		emit("load-job-dispatches");
	}
});

/**
 * Reload job dispatches when count increases while Jobs tab is active
 */
watch(() => props.jobDispatchCount, (newCount, oldCount) => {
	if (activeTab.value === "jobs" && newCount && oldCount && newCount > oldCount) {
		emit("load-job-dispatches");
	}
});

/**
 * Process HTML content and replace variable placeholders
 */
const processedHtml = computed(() => {
	let content = props.html || "";

	// If the LLM returned a full HTML document, extract just the body content
	const bodyMatch = content.match(/<body[^>]*>([\s\S]*)<\/body>/i);
	if (bodyMatch) {
		content = bodyMatch[1];
	}

	// Strip script tags - templates shouldn't have scripts and they're blocked by sandbox anyway
	content = content.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, "");

	// Replace variable placeholders with values or highlighted placeholders
	if (props.variables) {
		Object.entries(props.variables).forEach(([key, value]) => {
			const regex = new RegExp(`\\{\\{\\s*${key}\\s*\\}\\}`, "g");
			content = content.replace(regex, value);
		});
	}

	// Highlight remaining unresolved placeholders
	content = content.replace(
		/\{\{\s*(\w+)\s*\}\}/g,
		'<span class="template-variable">{{$1}}</span>'
	);

	return content;
});

/**
 * Complete iframe content with styles
 */
const iframeContent = computed(() => {
	const baseStyles = `
		body {
			margin: 0;
			padding: 16px;
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
			line-height: 1.5;
			color: #1e293b;
		}
		.template-variable {
			background-color: #fef3c7;
			border: 1px dashed #f59e0b;
			padding: 2px 6px;
			border-radius: 4px;
			font-family: monospace;
			font-size: 0.875em;
			color: #92400e;
		}
	`;

	const customStyles = props.css || "";

	return `
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<style>
				${baseStyles}
				${customStyles}
			</style>
		</head>
		<body>
			${processedHtml.value}
		</body>
		</html>
	`;
});

/**
 * Expose refs for screenshot capture
 */
defineExpose({
	containerRef,
	iframeRef
});
</script>
