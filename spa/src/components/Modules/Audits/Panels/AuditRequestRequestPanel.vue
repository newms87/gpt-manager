<template>
	<div class="p-6">
		<MarkdownEditor
			v-if="auditRequest.request"
			:model-value="auditRequest.request"
			format="json"
			readonly
		/>
		<QSkeleton v-else class="w-full rounded h-96" />
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import { AuditRequest } from "@/components/Modules/Audits/audit-requests";

defineProps<{
	auditRequest: AuditRequest,
}>();
</script>
