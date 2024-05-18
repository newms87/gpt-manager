<template>
	<div class="p-6">
		<template v-if="jobs.length === 0">
			<div class="text-xl text-center text-gray-500">No Jobs</div>
		</template>
		<template v-else>
			<DispatchedJobCard v-for="job in jobs" :key="job.id" class="mb-6" :job="job" />
		</template>
	</div>
</template>
<script setup lang="ts">
import { AuditRequest, JobDispatch } from "@/components/Modules/Audits/audit-requests";
import DispatchedJobCard from "@/components/Modules/Audits/DispatchedJobs/DispatchedJobCard";

defineProps<{
	auditRequest: AuditRequest,
	jobs: JobDispatch[],
}>();
</script>
