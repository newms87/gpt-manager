<template>
	<div class="p-6">
		<div class="flex flex-nowrap items-stretch justify-between">
			<div class="flex-grow mr-4">
				<SelectField
					v-model="workflowId"
					:loading="!workflows"
					class="w-full"
					:options="workflows?.map(w => ({label: w.name, value: w.id})) || []"
					placeholder="Select a workflow"
				/>
			</div>
			<QBtn
				class="text-lg mb-5 bg-lime-800 text-slate-300"
				:loading="runWorkflowAction.isApplying"
				:disable="runWorkflowAction.isApplying"
				@click="runWorkflowAction.trigger(null, {workflow_id: workflowId, input_source_id: inputSource.id})"
			>
				<RunIcon class="w-4 mr-3" />
				Run Workflow
			</QBtn>
		</div>

		<WorkflowRunCard
			v-for="workflowRun in inputSource.workflowRuns"
			:key="workflowRun.id"
			:workflow-run="workflowRun"
			class="mb-2"
		/>
	</div>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Modules/Workflows/workflowActions";
import { WorkflowRunCard } from "@/components/Modules/Workflows/WorkflowRuns";
import { WorkflowRoutes } from "@/routes/workflowRoutes";
import { InputSource } from "@/types/input-sources";
import { FaSolidCirclePlay as RunIcon } from "danx-icon";
import { SelectField } from "quasar-ui-danx";
import { onMounted, ref, shallowRef } from "vue";

defineProps<{
	inputSource: InputSource;
}>();

const workflowId = ref(null);
const workflows = shallowRef([]);
const runWorkflowAction = getAction("run-workflow");

onMounted(async () => {
	workflows.value = (await WorkflowRoutes.list({ page: 1, rowsPerPage: 1000 })).data;
});
</script>
