<template>
	<div class="flex items-center flex-nowrap space-x-4">
		<NodeArtifactsButton
			:count="taskRun?.input_artifacts_count"
			active-color="sky"
			:disabled="!taskRun"
			:artifacts="taskRun?.inputArtifacts"
			@show="onShowInputArtifacts"
		/>
		<div class="flex-grow flex items-center flex-nowrap space-x-2">
			<template v-if="taskRun">
				<WorkflowStatusTimerPill :runner="taskRun" class="text-xs" />
				<ActionButton
					v-if="isStopped"
					type="play"
					:action="resumeAction"
					:target="taskRun"
					color="green-invert"
					tooltip="Continue running task"
					class="p-2"
				/>
				<ActionButton
					v-else
					type="stop"
					:disabled="!isRunning"
					:action="stopAction"
					:target="taskRun"
					color="red"
					tooltip="Stop task"
					class="p-2"
				/>
				<ShowHideButton
					v-model="showTaskProcesses"
					:show-icon="ProcessListIcon"
				>
					<template #label>
						<div class="ml-2">{{ taskRun.process_count }}</div>
						<QMenu
							self="center right"
							max-height="80vh"
							:model-value="showTaskProcesses"
							@before-hide="showTaskProcesses = false"
							@hide="showTaskProcesses = false"
						>
							<ListTransition class="p-4 w-[60rem] h-[80vh] overflow-x-hidden overflow-y-auto">
								<QSkeleton v-if="taskRun.processes?.length === undefined" class="h-12" />
								<div
									v-else-if="taskRun.processes.length === 0"
									class="text-center text-gray-500 font-bold h-12 flex items-center justify-center"
								>
									There are no processes for this task
								</div>
								<NodeTaskProcessWidget
									v-for="taskProcess in taskRun.processes"
									:key="taskProcess.id"
									:task-process="taskProcess"
									class="bg-slate-700 p-4 my-2 rounded-lg"
								/>
							</ListTransition>
						</QMenu>
					</template>
				</ShowHideButton>
			</template>
		</div>
		<NodeArtifactsButton
			:count="taskRun?.output_artifacts_count"
			active-color="green"
			:disabled="!taskRun"
			:artifacts="taskRun?.outputArtifacts"
			@show="onShowOutputArtifacts"
		/>
	</div>
</template>

<script setup lang="ts">
import { dxTaskRun } from "@/components/Modules/TaskDefinitions/TaskRuns/config";
import { WorkflowStatusTimerPill } from "@/components/Modules/TaskWorkflows/Shared";
import NodeArtifactsButton from "@/components/Modules/WorkflowCanvas/NodeArtifactsButton";
import NodeTaskProcessWidget from "@/components/Modules/WorkflowCanvas/NodeTaskProcessWidget";
import { TaskRun } from "@/types";
import { FaSolidFileInvoice as ProcessListIcon } from "danx-icon";
import { ActionButton, autoRefreshObject, ListTransition, ShowHideButton, stopAutoRefreshObject } from "quasar-ui-danx";
import { computed, ref, watch } from "vue";

const props = defineProps<{
	taskRun?: TaskRun;
}>();

const resumeAction = dxTaskRun.getAction("resume");
const stopAction = dxTaskRun.getAction("stop");
const isStopped = computed(() => props.taskRun?.status === "Stopped" || props.taskRun?.status === "Pending");
const isRunning = computed(() => ["Running"].includes(props.taskRun?.status));

const artifactsField = {
	text_content: true,
	json_content: true,
	files: { transcodes: true, thumb: true }
};
async function onShowInputArtifacts() {
	await dxTaskRun.routes.details(props.taskRun, { inputArtifacts: artifactsField });
}
async function onShowOutputArtifacts() {
	await dxTaskRun.routes.details(props.taskRun, { outputArtifacts: artifactsField });
}

// Handle auto refreshing task processes while they're being shown
const showTaskProcesses = ref(false);
let autoRefreshId = "";
watch(() => props.taskRun, () => {
	if (props.taskRun) {
		autoRefreshId = "task-run-task-processes:" + props.taskRun?.id;
		autoRefreshObject(
			autoRefreshId,
			props.taskRun,
			(tr: TaskRun) => showTaskProcesses.value && (!tr.processes?.length || ["Running", "Pending"].includes(tr.status)),
			(tr: TaskRun) => dxTaskRun.routes.details(tr, {
				processes: {
					inputArtifacts: artifactsField,
					outputArtifacts: artifactsField
				}
			})
		);
	} else if (autoRefreshId) {
		stopAutoRefreshObject(autoRefreshId);
	}
});
</script>
