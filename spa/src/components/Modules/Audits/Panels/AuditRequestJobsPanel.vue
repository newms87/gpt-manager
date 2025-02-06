<template>
	<div class="p-6">
		<JobDispatchList :jobs="jobs" />
	</div>
</template>
<script setup lang="ts">
import { JobDispatch } from "@/components/Modules/Audits/audit-requests";
import JobDispatchList from "@/components/Modules/Audits/JobDispatches/JobDispatchList";

defineProps<{
	jobs: JobDispatch[],
}>();
</script>
