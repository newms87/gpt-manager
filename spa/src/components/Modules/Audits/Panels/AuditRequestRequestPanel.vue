<template>
	<div class="p-6">
		<MarkdownEditor
			class="markdown-code-only"
			:model-value="fMarkdownJSON(auditRequest.request)"
			readonly
		/>
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
