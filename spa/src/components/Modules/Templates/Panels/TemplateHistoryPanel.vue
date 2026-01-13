<template>
	<div class="p-4">
		<CollaborationVersionHistory
			:history="formattedHistory"
			:current-version="template.updated_at"
			@preview="onPreview"
			@restore="onRestore"
		/>
	</div>
</template>

<script setup lang="ts">
import { CollaborationVersionHistory } from "@/components/Modules/Collaboration";
import { dxTemplateDefinition } from "@/ui/templates/config";
import type { TemplateDefinition, TemplateDefinitionHistory } from "@/ui/templates/types";
import { computed } from "vue";

const props = defineProps<{
	template: TemplateDefinition;
}>();

const restoreAction = dxTemplateDefinition.getAction("restore-version");

/**
 * Format history entries for the version history component
 */
const formattedHistory = computed<TemplateDefinitionHistory[]>(() => {
	if (!props.template.history) return [];
	return props.template.history.map(entry => ({
		...entry,
		content: entry.html_content
	}));
});

/**
 * Preview a historical version
 */
function onPreview(historyId: number) {
	// Could open a modal showing the historical content
	console.log("Preview version:", historyId);
}

/**
 * Restore a historical version
 */
async function onRestore(historyId: number) {
	await restoreAction.trigger(props.template, { history_id: historyId });
}
</script>
