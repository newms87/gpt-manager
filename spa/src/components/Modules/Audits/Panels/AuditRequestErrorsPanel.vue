<template>
	<div class="p-6">
		<template v-if="auditRequest?.errors?.length === 0">
			<div class="text-xl text-center text-gray-500">No Errors</div>
		</template>
		<template v-else>
			<ErrorLogEntryCard v-for="error in auditRequest.errors" :key="error.id" class="mb-6" :error="error" />
		</template>
	</div>
</template>
<script setup lang="ts">
import { AuditRequest } from "@/components/Modules/Audits/audit-requests";
import ErrorLogEntryCard from "@/components/Modules/Audits/ErrorLogs/ErrorLogEntryCard";

defineProps<{
	auditRequest: AuditRequest,
}>();

</script>
