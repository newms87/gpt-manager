<template>
	<div class="p-4 space-y-4">
		<!-- Basic info -->
		<div class="space-y-3">
			<TextField
				:model-value="template.name"
				label="Name"
				required
				@update:model-value="name => updateAction.trigger(template, { name })"
			/>

			<TextField
				:model-value="template.description || ''"
				label="Description"
				type="textarea"
				@update:model-value="description => updateAction.trigger(template, { description })"
			/>

			<TextField
				:model-value="template.category || ''"
				label="Category"
				@update:model-value="category => updateAction.trigger(template, { category })"
			/>

			<SelectField
				:model-value="template.type"
				label="Type"
				:options="typeOptions"
				@update:model-value="type => updateAction.trigger(template, { type })"
			/>

			<div class="flex items-center justify-between">
				<span class="text-sm text-slate-300">Active</span>
				<QToggle
					:model-value="template.is_active"
					color="green"
					@update:model-value="is_active => updateAction.trigger(template, { is_active })"
				/>
			</div>
		</div>

		<!-- HTML Editor (for HTML templates) -->
		<template v-if="template.type === 'html'">
			<QSeparator class="bg-slate-600" />

			<div>
				<div class="text-sm font-semibold text-slate-300 mb-2">HTML Content</div>
				<MarkdownEditor
					:model-value="template.html_content || ''"
					mode="code"
					:language="'html'"
					editor-class="bg-slate-700 rounded min-h-[200px]"
					@update:model-value="html_content => updateAction.trigger(template, { html_content })"
				/>
			</div>

			<div>
				<div class="text-sm font-semibold text-slate-300 mb-2">CSS Content</div>
				<MarkdownEditor
					:model-value="template.css_content || ''"
					mode="code"
					:language="'css'"
					editor-class="bg-slate-700 rounded min-h-[100px]"
					@update:model-value="css_content => updateAction.trigger(template, { css_content })"
				/>
			</div>
		</template>

		<!-- Google Docs info (for Google Docs templates) -->
		<template v-else-if="template.type === 'google_docs'">
			<QSeparator class="bg-slate-600" />

			<div>
				<div class="text-sm font-semibold text-slate-300 mb-2">Google Docs File</div>
				<div v-if="template.stored_file" class="bg-slate-700 rounded p-3">
					<FilePreview :file="template.stored_file" downloadable />
				</div>
				<div v-else class="text-slate-400 text-sm">
					No Google Docs file linked
				</div>
			</div>
		</template>

		<!-- Open Builder button -->
		<div class="pt-4">
			<ActionButton
				type="edit"
				label="Open Template Builder"
				color="sky-invert"
				class="w-full"
				@click="openBuilder"
			/>
		</div>
	</div>
</template>

<script setup lang="ts">
import { dxTemplateDefinition } from "@/ui/templates/config";
import type { TemplateDefinition } from "@/ui/templates/types";
import { ActionButton, FilePreview, MarkdownEditor, SelectField, TextField } from "quasar-ui-danx";
import { useRouter } from "vue-router";

const props = defineProps<{
	template: TemplateDefinition;
}>();

const router = useRouter();

const updateAction = dxTemplateDefinition.getAction("update");

const typeOptions = [
	{ label: "HTML", value: "html" },
	{ label: "Google Docs", value: "google_docs" }
];

/**
 * Navigate to template builder
 */
function openBuilder() {
	router.push({ name: "ui.template-builder", params: { id: props.template.id } });
}
</script>
