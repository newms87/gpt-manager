<template>
	<div class="p-6 flex flex-col items-stretch flex-nowrap">
		<div v-if="jobFlowDiagram" class="mb-4 h-56">
			<RenderDiagram :diagram="jobFlowDiagram" class="bg-sky-900 rounded h-full" theme="dark" />
		</div>
		<div class="flex-grow">
			<ListTransition>
				<WorkflowJobCard
					v-for="job in workflow.jobs"
					:key="job.id"
					class="mb-5"
					:job="job"
					:workflow="workflow"
					:is-tool="!!job.workflow_tool"
				/>
			</ListTransition>

			<QBtn
				class="text-lg w-full mb-5 bg-lime-800 text-slate-300"
				:loading="createJobAction.isApplying"
				@click="createJobAction.trigger(workflow)"
			>
				<CreateIcon class="w-4 mr-3" />
				Add Job
			</QBtn>
		</div>
	</div>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Modules/Workflows/workflowActions";
import WorkflowJobCard from "@/components/Modules/Workflows/WorkflowJobs/WorkflowJobCard";
import RenderDiagram from "@/components/Shared/Diagrams/RenderDiagram";
import { Workflow } from "@/types/workflows";
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
		const taskCount = Object.keys(job.tasks_preview).length;
		const tasksStr = job.tasks_preview ? `\n${taskCount} ${taskCount === 1 ? "task" : "tasks"}` : "";
		diagram += `${job.id}(${job.name + tasksStr})\n`;
	}

	for (const job of props.workflow.jobs) {
		if (job.dependencies?.length > 0) {
			diagram += `${job.dependencies.map(d => d.depends_on_id).join(" & ")}--> ${job.id}\n`;
		}
	}

	return diagram;
});
</script>
