<template>
	<div>
		<ListTransition>
			<template v-for="assignment in job.assignments" :key="assignment.id">
				<QSeparator class="bg-slate-200" />
				<WorkflowAssignmentItem
					:assignment="assignment"
					context="workflow"
					:unassign-action="unassignAgentAction"
				/>
			</template>
		</ListTransition>
		<div class="mt-4 flex items-stretch gap-x-4">
			<SelectField
				class="flex-grow"
				:options="availableAgents"
				:disable="assignAgentAction.isApplying"
				placeholder="+ Assign Agent"
				@update="assignAgentAction.trigger(job, {ids: [$event]})"
			/>
			<ActionButton :action="createAgentAction" type="create" class="bg-lime-900 px-8" />
		</div>
	</div>
</template>
<script setup lang="ts">
import { dxAgent } from "@/components/Modules/Agents";
import { dxWorkflow } from "@/components/Modules/Workflows";
import WorkflowAssignmentItem from "@/components/Modules/Workflows/WorkflowJobs/WorkflowAssignmentItem";
import { ActionButton } from "@/components/Shared";
import { WorkflowJob } from "@/types/workflows";
import { ListTransition, SelectField } from "quasar-ui-danx";
import { computed } from "vue";

const props = defineProps<{
	job: WorkflowJob;
}>();

const createAgentAction = dxAgent.extendAction("create", props.job.id, {
	onFinish: async (result) => {
		await assignAgentAction.trigger(props.job, { ids: [result.item.id] });
		await dxWorkflow.loadFieldOptions();
	}
});
const assignAgentAction = dxWorkflow.getAction("assign-agent");
const unassignAgentAction = dxWorkflow.getAction("unassign-agent");

const availableAgents = computed(() => dxWorkflow.getFieldOptions("agents").filter(a => !props.job.assignments.find(ja => ja.agent && ja.agent.id === a.value)));
</script>
