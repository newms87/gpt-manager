<template>
	<div class="relative h-18">
		<div class="flex-x space-x-4">
			<div v-if="activeWorkflowRun" class="flex-grow flex items-center space-x-4 flex-nowrap">
				<LabelPillWidget
					:label="`WorkflowRun: ${activeWorkflowRun.id}`"
					color="sky"
					size="xs"
				/>
				<EditableDiv
					v-model="activeWorkflowRun.name"
					color="sky-900"
					@change="name => updateWorkflowRunAction.trigger(activeWorkflowRun, {name})"
				/>
				<ActionButton
					v-if="isRunning"
					type="stop"
					color="red"
					size="sm"
					tooltip="Stop Workflow"
					:action="stopWorkflowRunAction"
					:target="activeWorkflowRun"
				/>
				<ActionButton
					v-if="isStopped"
					type="play"
					color="sky"
					size="sm"
					tooltip="Resume Workflow"
					:action="resumeWorkflowRunAction"
					:target="activeWorkflowRun"
				/>
				<WorkflowStatusTimerPill :runner="activeWorkflowRun" timer-class="px-4 bg-slate-800 rounded-full" />
			</div>
			<div v-if="activeWorkflowRun" class="px-2">
				<QSeparator class="bg-slate-400 h-6" vertical />
			</div>
			<div>
				<ActionButton
					type="play"
					color="green"
					label="Run Workflow"
					:disabled="isRunning"
					@click="onRunWorkflow"
				/>
			</div>
			<div>
				<ShowHideButton
					v-model="isShowing"
					:label="`${activeWorkflowDefinition.runs?.length || 0}`"
					color="green-invert"
					:show-icon="RunsIcon"
				/>
			</div>
		</div>
		<div
			class="absolute-top-right top-[120%] z-10 transition-all overflow-y-auto max-h-[80vh] w-[80vw] bg-sky-900 px-4"
			:class="{'h-[5000%]': isShowing, 'h-0': !isShowing}"
		>
			<div v-if="isShowing" class="mt-4">
				<div v-if="activeWorkflowDefinition.runs?.length === 0" class="text-center text-sky-300">No Workflow Runs</div>
				<div v-else>
					<WorkflowRunCard
						v-for="run in activeWorkflowDefinition.runs"
						:key="run.id"
						:workflow-definition="activeWorkflowDefinition"
						:workflow-run="run"
						class="my-2"
						selectable
						@select="onSelectActiveWorkflowRun(run)"
					/>
				</div>
			</div>
			<SelectWorkflowInputDialog
				v-if="isSelectingWorkflowInput"
				@confirm="onCreateWorkflowRun"
				@close="isSelectingWorkflowInput = false"
			/>
		</div>
	</div>
</template>
<script setup lang="ts">
import { WorkflowStatusTimerPill } from "@/components/Modules/WorkflowDefinitions/Shared";
import {
	activeWorkflowDefinition,
	activeWorkflowRun,
	createWorkflowRun,
	refreshWorkflowRun
} from "@/components/Modules/WorkflowDefinitions/store";
import SelectWorkflowInputDialog
	from "@/components/Modules/WorkflowDefinitions/WorkflowInputs/SelectWorkflowInputDialog";
import WorkflowRunCard from "@/components/Modules/WorkflowDefinitions/WorkflowRunCard";
import { dxWorkflowRun } from "@/components/Modules/WorkflowDefinitions/WorkflowRuns/config";
import { WorkflowInput, WorkflowRun } from "@/types";
import { FaSolidPersonRunning as RunsIcon } from "danx-icon";
import { ActionButton, EditableDiv, LabelPillWidget, ShowHideButton } from "quasar-ui-danx";
import { computed, ref } from "vue";

defineEmits(["confirm", "close"]);

const isShowing = ref(false);
const isSelectingWorkflowInput = ref(false);
const updateWorkflowRunAction = dxWorkflowRun.getAction("update");
const stopWorkflowRunAction = dxWorkflowRun.getAction("stop");
const resumeWorkflowRunAction = dxWorkflowRun.getAction("resume");
const isRunning = computed(() => ["Running", "Pending"].includes(activeWorkflowRun.value?.status));
const isStopped = computed(() => ["Stopped"].includes(activeWorkflowRun.value?.status));
const hasWorkflowInputNode = computed(() => !!activeWorkflowDefinition.value?.nodes.find((node) => node.taskDefinition.task_runner_name === "Workflow Input"));

function onRunWorkflow() {
	if (!activeWorkflowDefinition.value) return;

	if (hasWorkflowInputNode.value) {
		isSelectingWorkflowInput.value = true;
	} else {
		onCreateWorkflowRun();
	}
}

async function onCreateWorkflowRun(workflowInput?: WorkflowInput) {
	if (!activeWorkflowDefinition.value) return;
	isSelectingWorkflowInput.value = false;

	await createWorkflowRun(workflowInput);
}
async function onSelectActiveWorkflowRun(workflowRun: WorkflowRun) {
	activeWorkflowRun.value = workflowRun;
	isShowing.value = false;
	await refreshWorkflowRun(workflowRun);
}
</script>
