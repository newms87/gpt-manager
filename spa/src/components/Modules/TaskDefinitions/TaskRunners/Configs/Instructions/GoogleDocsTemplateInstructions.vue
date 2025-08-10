<template>
	<div class="mt-8 bg-slate-800 rounded-xl shadow-lg p-6">
		<div class="flex-x gap-2 mb-6">
			<span class="text-blue-400 text-2xl">📄</span>
			<h1 class="text-2xl font-bold text-white">Configure Google Docs Template Task</h1>
			<p class="text-sm text-slate-300">
				Generate Google Docs from templates with dynamic variable substitution. Variables are automatically extracted from input artifacts and used to populate template placeholders.
			</p>
		</div>

		<div class="flex gap-16 justify-between text-slate-200">

			<!-- Step 1: Template Setup -->
			<div class="flex-1 min-w-[300px]">
				<div class="flex items-center gap-2 mb-2 text-sky-300 font-semibold text-lg">
					<TemplateIcon class="w-5 text-sky-500" />
					<span>1. Template Setup</span>
					<div>
						<HelpIcon class="w-4" />
						<QTooltip class="text-base">
							<div class="max-w-sm">
								Create a Google Doc template with placeholder variables using double curly braces.
								<ul class="list-disc ml-4 mt-2 text-slate-100">
									<li>Use <code>{{variable_name}}</code> for simple values</li>
									<li>Use <code>{{nested.property}}</code> for nested objects</li>
									<li>Variables are case-sensitive</li>
								</ul>
							</div>
						</QTooltip>
					</div>
				</div>

				<div class="text-sm space-y-2">
					<div class="bg-blue-900 border-l-4 border-blue-600 p-3 rounded">
						<div class="font-semibold text-blue-200 mb-2">📝 Template Example:</div>
						<div class="bg-slate-900 p-2 rounded font-mono text-xs">
							Patient: {{patient_name}}<br/>
							Diagnosis: {{diagnosis}}<br/>
							Treatment: {{treatment.medication}}<br/>
							Date: {{created_at}}
						</div>
					</div>
				</div>
			</div>

			<!-- Step 2: Artifact Requirements -->
			<div class="flex-1 min-w-[300px]">
				<div class="flex items-center gap-2 mb-2 text-sky-300 font-semibold text-lg">
					<ArtifactIcon class="w-5 text-sky-500" />
					<span>2. Artifact Structure</span>
					<div>
						<HelpIcon class="w-4" />
						<QTooltip class="text-base">
							<div class="max-w-sm">
								Input artifacts must contain the Google Doc template file ID and variable data.
								<ul class="list-disc ml-4 mt-2 text-slate-100">
									<li><strong>google_doc_file_id:</strong> Required in artifact meta</li>
									<li><strong>Variables:</strong> Extracted from json_content, meta, and text_content</li>
									<li><strong>Text parsing:</strong> Supports "key: value" and "key = value" patterns</li>
								</ul>
							</div>
						</QTooltip>
					</div>
				</div>

				<div class="text-sm space-y-2">
					<div class="bg-green-900 border-l-4 border-green-600 p-3 rounded">
						<div class="font-semibold text-green-200 mb-2">📋 Required Artifact:</div>
						<div class="bg-slate-900 p-2 rounded font-mono text-xs text-green-300">
							{<br/>
							&nbsp;&nbsp;"meta": {<br/>
							&nbsp;&nbsp;&nbsp;&nbsp;"google_doc_file_id": "1ABC...XYZ"<br/>
							&nbsp;&nbsp;},<br/>
							&nbsp;&nbsp;"json_content": {<br/>
							&nbsp;&nbsp;&nbsp;&nbsp;"patient_name": "John Doe",<br/>
							&nbsp;&nbsp;&nbsp;&nbsp;"diagnosis": "Hypertension"<br/>
							&nbsp;&nbsp;}<br/>
							}
						</div>
					</div>
				</div>
			</div>

			<!-- Step 3: Variable Extraction -->
			<div class="flex-1 min-w-[300px]">
				<div class="flex items-center gap-2 mb-2 text-sky-300 font-semibold text-lg">
					<VariableIcon class="w-5 text-sky-500" />
					<span>3. Variable Extraction</span>
					<div>
						<HelpIcon class="w-4" />
						<QTooltip class="text-base">
							<div class="max-w-sm">
								Variables are automatically extracted from multiple sources and flattened with dot notation.
								<ul class="list-disc ml-4 mt-2 text-slate-100">
									<li><strong>JSON Content:</strong> All nested properties flattened</li>
									<li><strong>Meta:</strong> Artifact metadata fields</li>
									<li><strong>Text Content:</strong> Parsed "key: value" patterns</li>
								</ul>
							</div>
						</QTooltip>
					</div>
				</div>

				<div class="text-sm space-y-2">
					<div class="bg-purple-900 border-l-4 border-purple-600 p-3 rounded">
						<div class="font-semibold text-purple-200 mb-2">🔄 Extraction Examples:</div>
						<div class="space-y-2">
							<div class="bg-slate-900 p-2 rounded">
								<div class="text-purple-300 text-xs font-semibold mb-1">Nested Object:</div>
								<div class="font-mono text-xs">
									{"treatment": {"medication": "Aspirin"}}<br/>
									→ treatment.medication = "Aspirin"
								</div>
							</div>
							<div class="bg-slate-900 p-2 rounded">
								<div class="text-purple-300 text-xs font-semibold mb-1">Text Parsing:</div>
								<div class="font-mono text-xs">
									"Patient Name: John Doe"<br/>
									→ patient_name = "John Doe"
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

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
				The resulting artifact will contain the <strong>google_doc_url</strong> in its meta field for easy access to the generated document.
			</p>
		</div>
	</div>
</template>

<script setup lang="ts">
import {
	FaSolidCircleQuestion as HelpIcon,
	FaSolidFile as TemplateIcon,
	FaSolidBox as ArtifactIcon,
	FaSolidCode as VariableIcon,
	FaSolidServer as ServerIcon,
	FaSolidFileExport as OutputIcon
} from "danx-icon";
</script>