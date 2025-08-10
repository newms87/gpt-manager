<template>
	<div class="mt-8 bg-slate-800 rounded-xl shadow-lg p-6">
		<div class="flex-x gap-2 mb-6">
			<span class="text-blue-400 text-2xl">📄</span>
			<h1 class="text-2xl font-bold text-white">Configure Google Docs Template Task</h1>
			<p class="text-sm text-slate-300">
				Generate Google Docs from templates with dynamic variable substitution. Variables are automatically extracted
				from input artifacts and used to populate template placeholders.
			</p>
		</div>

		<!-- MCP Server Requirement -->
		<div class="mt-6 bg-amber-900 border border-amber-600 rounded-lg p-4">
			<div class="flex items-center gap-2 mb-2">
				<ServerIcon class="w-5 text-amber-400" />
				<h3 class="text-lg font-semibold text-amber-200">MCP Server Requirement</h3>
			</div>
			<p class="text-sm text-amber-100">
				This task runner requires the <strong>@zapier/mcp</strong> server to be configured with Google Docs access.
				The system uses the <code>google_docs_create_document_from_template</code> tool to generate documents.
				Make sure your MCP server is properly authenticated with Google Workspace.
			</p>
		</div>

		<!-- Output Information -->
		<div class="mt-4 bg-slate-700 border border-slate-500 rounded-lg p-4">
			<div class="flex items-center gap-2 mb-2">
				<OutputIcon class="w-5 text-slate-300" />
				<h3 class="text-lg font-semibold text-slate-200">Output</h3>
			</div>
			<p class="text-sm text-slate-300">
				The task generates a new Google Doc from your template with all variables substituted.
				The resulting artifact will contain the <strong>google_doc_url</strong> in its meta field for easy access to the
				generated document.
			</p>
		</div>
	</div>
</template>

<script setup lang="ts">
import { FaSolidFileExport as OutputIcon, FaSolidServer as ServerIcon } from "danx-icon";
</script>
