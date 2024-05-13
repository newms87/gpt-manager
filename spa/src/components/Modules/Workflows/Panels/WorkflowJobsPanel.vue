<template>
	<div class="p-6 flex items-stretch flex-nowrap">
		<div class="flex-grow">
			<ListTransition>
				<WorkflowJobCard v-for="job in workflow.jobs" :key="job.id" class="mb-5" :job="job" :workflow="workflow" />
			</ListTransition>

			<QBtn
				class="text-lg w-full mb-5 bg-lime-800 text-slate-300"
				:loading="createJobAction.isApplying"
				:disable="createJobAction.isApplying"
				@click="createJobAction.trigger(workflow)"
			>
				<CreateIcon class="w-4 mr-3" />
				Add Job
			</QBtn>
		</div>
		<div v-if="jobFlowDiagram" class="pl-4">
			<RenderDiagram :diagram="jobFlowDiagram" class="bg-sky-900 rounded" theme="dark" />
		</div>
	</div>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Modules/Workflows/workflowActions";
import WorkflowJobCard from "@/components/Modules/Workflows/WorkflowJobs/WorkflowJobCard";
import { Workflow } from "@/components/Modules/Workflows/workflows";
import RenderDiagram from "@/components/Shared/Diagrams/RenderDiagram";
import { FaSolidAddressCard as CreateIcon } from "danx-icon";
import { ListTransition } from "quasar-ui-danx";
import { computed } from "vue";

const props = defineProps<{
	workflow: Workflow,
}>();

const createJobAction = getAction("create-job");
const jobFlowDiagram = computed(() => {
	if (!props.workflow.jobs) return;

	let diagram = "";
	for (const job of props.workflow.jobs) {
		diagram += `${job.id}(${job.name})\n`;
	}

	for (const job of props.workflow.jobs) {
		if (job.depends_on?.length > 0) {
			diagram += `${job.depends_on.join(" & ")}--> ${job.id}\n`;
		}
	}

	return diagram;
});
</script>
