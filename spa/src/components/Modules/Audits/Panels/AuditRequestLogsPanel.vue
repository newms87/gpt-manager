<template>
	<div class="p-6">
		<AuditRequestLogsCard :logs="auditRequest.logs" />
	</div>
</template>
<script setup lang="ts">
import { AuditRequest } from "@/components/Modules/Audits/audit-requests";
import AuditRequestLogsCard from "@/components/Modules/Audits/AuditRequestLogs/AuditRequestLogsCard";

defineProps<{
	auditRequest: AuditRequest,
}>();
</script>
