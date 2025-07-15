<template>
	<div class="py-2 px-4 border rounded-lg bg-slate-800">
		<div class="flex-x gap-2">
			<LabelPillWidget :label="`WorkflowRun: ${workflowRun.id}`" color="sky" size="xs" />
			<LabelPillWidget :label=" fDateTime(workflowRun.created_at)" color="gray" size="xs" />
			<div class="flex-grow font-bold">{{ workflowRun.name }}</div>
			<UsageVisualizationButton v-if="workflowRun.usage" :usage="workflowRun.usage" />
			<WorkflowStatusTimerPill :runner="workflowRun" />
			<ShowHideButton v-model="isShowing" class="bg-sky-900" />
			<ActionButton
				v-if="selectable"
				type="confirm"
				color="green-invert"
				label="select"
				class="text-xs"
				@click="$emit('select')"
			/>
			<ActionButton type="trash" color="red" :action="deleteWorkflowRunAction" :target="workflowRun" />
		</div>
		<div v-if="isShowing" class="py-4">
			<TaskRunCard v-for="taskRun in workflowRun.taskRuns" :key="taskRun.id" :task-run="taskRun" class="my-2" />
		</div>
	</div>
</template>
<script setup lang="ts">
import TaskRunCard from "@/components/Modules/TaskDefinitions/Panels/TaskRunCard";
import UsageVisualizationButton from "@/components/Shared/Usage/UsageVisualizationButton";
import { dxWorkflowDefinition } from "@/components/Modules/WorkflowDefinitions/config";
import { WorkflowStatusTimerPill } from "@/components/Modules/WorkflowDefinitions/Shared";
import { dxWorkflowRun } from "@/components/Modules/WorkflowDefinitions/WorkflowRuns/config";
import { WorkflowDefinition, WorkflowRun } from "@/types";
import { ActionButton, fDateTime, LabelPillWidget, ShowHideButton } from "quasar-ui-danx";
import { ref } from "vue";

defineEmits(["select"]);
const props = defineProps<{
	workflowDefinition: WorkflowDefinition;
	workflowRun: WorkflowRun;
	selectable?: boolean;
}>();

const deleteWorkflowRunAction = dxWorkflowRun.getAction("delete", { onFinish: () => dxWorkflowDefinition.routes.details(props.workflowDefinition) });
const isShowing = ref(false);
</script>
