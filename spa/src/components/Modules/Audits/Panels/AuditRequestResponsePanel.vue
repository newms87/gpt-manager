<template>
	<div class="p-6">
		<MarkdownEditor
			v-if="auditRequest.response"
			class="markdown-code-only"
			:model-value="fMarkdownJSON(auditRequest.response)"
			readonly
		/>
		<div v-else class="text-lg text-gray-500">No response</div>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MardownEditor/MarkdownEditor";
import { AuditRequest } from "@/components/Modules/Audits/audit-requests";
import { fMarkdownJSON } from "quasar-ui-danx";

defineProps<{
	auditRequest: AuditRequest,
}>();
</script>
