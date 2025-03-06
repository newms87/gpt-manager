<template>
	<CollapsableSidebar
		v-model:collapse="isCollapsed"
		class="workflow-canvas-sidebar"
		right-side
		min-width="3.5rem"
		max-width="20rem"
		name="workflow-canvas-sidebar"
	>
		<div class="h-full flex overflow-hidden flex-col flex-nowrap">
			<div class="sidebar-title flex-shrink-0 text-center pt-4">
				<div class="flex items-center justify-center w-full">
					<ActionButton
						type="create"
						color="green"
						:action="createTaskAction"
						class="p-2"
						icon-class="w-4"
						:tooltip="isCollapsed ? 'Create new task' : ''"
						:label="isCollapsed ? '' : 'Create new task'"
						@success="({item}) => afterCreateTask(item)"
					/>
				</div>
			</div>
			<div
				class="node-task-list flex-grow overflow-y-auto overflow-x-hidden pt-4 flex flex-col flex-nowrap space-y-4"
				:class="{'overflow-y-hidden': isCollapsed}"
			>
				<LabelPillWidget
					v-for="task in availableTasks"
					:key="task.id"
					class="node-task flex items-center flex-nowrap cursor-pointer group max-w-full overflow-hidden flex-shrink-0"
					color="sky"
					size="sm"
					draggable="true"
					@dragstart="onDragStart($event, task)"
					@click="onAddTask(task)"
				>
					<div class="node-task-icon p-1">
						<NodeTaskIcon class="w-6" />
						<QTooltip v-if="isCollapsed">{{ task.name }}</QTooltip>
					</div>
					<div v-if="!isCollapsed" class="flex items-center flex-nowrap ml-2 flex-grow max-w-full overflow-hidden">
						<div class="flex-grow max-w-full overflow-hidden">
							<div class="node-item-title whitespace-nowrap">{{ task.name }}</div>
							<QTooltip v-if="task.description" class="text-sm text-slate-300 bg-slate-700 p-3 rounded">{{
									task.description
								}}
							</QTooltip>
						</div>
						<ActionButton
							type="trash"
							:action="deleteTaskAction"
							:target="task"
							class="opacity-0 group-hover:opacity-100 transition-all"
						/>
					</div>
				</LabelPillWidget>
			</div>
		</div>
	</CollapsableSidebar>
</template>

<script setup lang="ts">
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { addWorkflowNode } from "@/components/Modules/TaskWorkflows/store";
import { onDragStart } from "@/components/Modules/WorkflowCanvas/dragNDrop";
import { TaskDefinition } from "@/types";
import { TaskWorkflow } from "@/types/task-workflows";
import { FaSolidSquareShareNodes as NodeTaskIcon } from "danx-icon";
import { ActionButton, CollapsableSidebar, LabelPillWidget } from "quasar-ui-danx";
import { onMounted, ref, shallowRef } from "vue";

const emit = defineEmits(["refresh"]);
defineProps<{
	taskWorkflow: TaskWorkflow;
}>();

const isCollapsed = ref(false);
const createTaskAction = dxTaskDefinition.getAction("create");
const deleteTaskAction = dxTaskDefinition.getAction("delete", { onFinish: async () => emit("refresh") || await loadTaskDefinitions() });

// Define available node types with descriptions
const availableTasks = shallowRef([]);

onMounted(loadTaskDefinitions);

async function loadTaskDefinitions() {
	availableTasks.value = (await dxTaskDefinition.routes.list()).data;
}

async function onAddTask(taskDefinition: TaskDefinition) {
	await addWorkflowNode(taskDefinition);
}

async function afterCreateTask(taskDefinition: TaskDefinition) {
	loadTaskDefinitions();
	await onAddTask(taskDefinition);
}

</script>
