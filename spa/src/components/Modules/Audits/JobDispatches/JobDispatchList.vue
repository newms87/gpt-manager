<template>
	<ListTransition class="overflow-hidden w-full">
		<QSkeleton v-if="!jobs" class="h-20 my-2" />
		<div v-else-if="jobs.length === 0" class="text-xl text-center text-gray-500">No Job Dispatches</div>
		<template v-else>
			<JobDispatchCard v-for="job in jobs" :key="job.id" class="my-2" :job="job" />
		</template>
	</ListTransition>
</template>
<script setup lang="ts">
import { JobDispatch } from "@/components/Modules/Audits/audit-requests";
import JobDispatchCard from "@/components/Modules/Audits/JobDispatches/JobDispatchCard";
import { ListTransition } from "quasar-ui-danx";

defineProps<{
	jobs?: JobDispatch[];
}>();
</script>
