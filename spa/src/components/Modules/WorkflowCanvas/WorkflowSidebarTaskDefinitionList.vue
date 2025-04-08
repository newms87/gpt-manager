<template>
	<div class="h-full flex overflow-hidden flex-col flex-nowrap">
		<div class="sidebar-title flex-shrink-0 text-center pt-4">
			<div class="flex items-center justify-center w-full">
				<ActionButton
					type="create"
					color="green"
					:action="createTaskAction"
					class="p-2"
					icon-class="w-4"
					:tooltip="collapsed ? 'Create new task' : ''"
					:label="collapsed ? '' : 'Create new task'"
					@success="({item}) => afterCreateTask(item)"
				/>
			</div>
		</div>
		<div
			class="node-task-list flex-grow overflow-y-auto overflow-x-hidden pt-4 flex flex-col flex-nowrap space-y-4"
			:class="{'overflow-y-hidden': collapsed, 'px-4': !collapsed}"
		>
			<LabelPillWidget
				v-for="task in taskDefinitions"
				:key="task.id"
				class="node-task flex-x cursor-pointer group max-w-full overflow-hidden flex-shrink-0"
				color="sky"
				size="sm"
				draggable="true"
				@dragstart="onDragStart($event, task)"
				@click="onAddTask(task)"
			>
				<div class="node-task-icon p-1">
					<NodeTaskIcon class="w-6" />
					<QTooltip v-if="collapsed">{{ task.name }}</QTooltip>
				</div>
				<div v-if="!collapsed" class="flex-x ml-2 flex-grow max-w-full overflow-hidden">
					<div class="flex-grow max-w-full overflow-hidden">
						<div class="node-item-title whitespace-nowrap">{{ task.name }}</div>
						<QTooltip v-if="task.description" class="text-sm text-slate-300 bg-slate-700 p-3 rounded">
							{{ task.description }}
						</QTooltip>
					</div>
					<ActionButton
						type="trash"
						:action="deleteTaskAction"
						:target="task"
						class="opacity-0 group-hover:opacity-100 transition-all"
						@click.stop
					/>
				</div>
			</LabelPillWidget>
		</div>
	</div>
</template>

<script setup lang="ts">
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { onDragStart } from "@/components/Modules/WorkflowCanvas/dragNDrop";
import { loadTaskDefinitions, taskDefinitions } from "@/components/Modules/WorkflowCanvas/helpers";
import { addWorkflowNode } from "@/components/Modules/WorkflowDefinitions/store";
import { TaskDefinition, WorkflowDefinition } from "@/types";
import { FaSolidSquareShareNodes as NodeTaskIcon } from "danx-icon";
import { ActionButton, LabelPillWidget } from "quasar-ui-danx";
import { onMounted } from "vue";

const emit = defineEmits(["refresh"]);
defineProps<{
	workflowDefinition: WorkflowDefinition;
	collapsed?: boolean;
}>();

const createTaskAction = dxTaskDefinition.getAction("create");
const deleteTaskAction = dxTaskDefinition.getAction("delete", { onFinish: async () => emit("refresh") || await loadTaskDefinitions() });

onMounted(loadTaskDefinitions);

async function onAddTask(taskDefinition: TaskDefinition) {
	await addWorkflowNode(taskDefinition);
}

async function afterCreateTask(taskDefinition: TaskDefinition) {
	loadTaskDefinitions();
	await onAddTask(taskDefinition);
}

</script>
