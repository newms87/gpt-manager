<template>
	<div class="p-6">
		<MarkdownEditor
			v-if="auditRequest.request"
			class="markdown-code-only"
			:model-value="fMarkdownJSON(auditRequest.request)"
			readonly
		/>
		<QSkeleton v-else class="w-full rounded h-96" />
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
