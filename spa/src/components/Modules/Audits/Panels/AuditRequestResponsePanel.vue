<template>
	<div class="p-6">
		<MarkdownEditor
			v-if="auditRequest.response"
			class="markdown-code-only"
			:model-value="auditRequest.response"
			force-json
			readonly
		/>
		<div v-else-if="auditRequest.response === null" class="text-xl text-center text-gray-500">No response</div>
		<QSkeleton v-else class="w-full rounded h-96" />
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MardownEditor/MarkdownEditor";
import { AuditRequest } from "@/components/Modules/Audits/audit-requests";

defineProps<{
	auditRequest: AuditRequest,
}>();
</script>
