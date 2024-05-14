<template>
	<div class="p-6">
		<template v-if="auditRequest?.api_logs?.length === 0">
			<div class="text-xl text-center text-gray-500">No Api Log Entries</div>
		</template>
		<template v-else>
			<div v-for="apiLog in auditRequest.api_logs" :key="apiLog.id" class="mb-6">
				{{ apiLog }}
			</div>
		</template>
	</div>
</template>
<script setup lang="ts">
import { AuditRequest } from "@/components/Modules/Audits/audit-requests";

defineProps<{
	auditRequest: AuditRequest,
}>();
</script>
