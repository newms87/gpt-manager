<template>
	<div class="p-6">
		<CodeViewer
			v-if="auditRequest.request"
			:model-value="auditRequest.request"
			format="json"
		/>
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
