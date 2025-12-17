<template>
	<div class="p-6">
		<CodeViewer
			v-if="auditRequest.response"
			:model-value="auditRequest.response"
			format="json"
		/>
		<div v-else-if="auditRequest.response === null" class="text-xl text-center text-gray-500">No response</div>
		<QSkeleton v-else class="w-full rounded h-96" />
	</div>
</template>
<script setup lang="ts">
import { AuditRequest } from "@/components/Modules/Audits/audit-requests";
import { CodeViewer } from "quasar-ui-danx";

defineProps<{
	auditRequest: AuditRequest,
}>();
</script>
