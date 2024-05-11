<template>
	<div class="p-6">
		<QBtn
			class="text-lg w-full mb-5 bg-lime-800 text-slate-300"
			:loading="createJobAction.isApplying"
			:disable="createJobAction.isApplying"
			@click="createJobAction.trigger(workflow)"
		>
			<CreateIcon class="w-4 mr-3" />
			Create Job
		</QBtn>

		<WorkflowJobCard v-for="job in workflow.jobs" :key="job.id" class="mb-5" :job="job" />
	</div>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Workflows/workflowActions";
import WorkflowJobCard from "@/components/Workflows/WorkflowJobs/WorkflowJobCard";
import { Workflow } from "@/components/Workflows/workflows";
import { FaSolidAddressCard as CreateIcon } from "danx-icon";

defineProps<{
	workflow: Workflow,
}>();

const createJobAction = getAction("create-job");
</script>
