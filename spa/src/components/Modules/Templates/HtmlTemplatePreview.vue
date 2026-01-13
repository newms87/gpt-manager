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
					sandbox="allow-same-origin"
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
		</div>
	</div>
</template>

<script setup lang="ts">
import {
	FaSolidCode as CodeIcon,
	FaSolidEye as EyeIcon,
	FaSolidFileCode as DocumentIcon,
	FaSolidHashtag as CssIcon,
	FaSolidTags as HtmlIcon
} from "danx-icon";
import { CodeViewer } from "quasar-ui-danx";
import { computed, ref } from "vue";

const props = withDefaults(defineProps<{
	html: string;
	css?: string;
	variables?: Record<string, string>;
}>(), {
	css: "",
	variables: () => ({})
});

const containerRef = ref<HTMLElement | null>(null);
const iframeRef = ref<HTMLIFrameElement | null>(null);
const activeTab = ref<"preview" | "code">("preview");

/**
 * Process HTML content and replace variable placeholders
 */
const processedHtml = computed(() => {
	let content = props.html || "";

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
