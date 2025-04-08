<template>
	<div
		class="task-runner-classes-list flex-grow overflow-y-auto overflow-x-hidden pt-4 flex flex-col flex-nowrap space-y-4"
		:class="{'overflow-y-hidden': collapsed, 'px-4': !collapsed}"
	>
		<LabelPillWidget
			v-for="taskRunnerClass in TaskRunnerClasses.list()"
			:key="taskRunnerClass.name"
			class="node-task flex-x cursor-pointer group max-w-full overflow-hidden flex-shrink-0"
			:class="{'w-12 !px-1': collapsed}"
			color="sky"
			size="xs"
			draggable="true"
			@mouseenter="isHoveringName = taskRunnerClass.name"
			@mouseleave="isHoveringName = ''"
			@dragstart="onDragStart($event, taskRunnerClass)"
			@click="addWorkflowNode(taskRunnerClass)"
		>
			<div class="node-task-icon">
				<Component
					:is="taskRunnerClass.lottie"
					class="w-10 h-10 p-1"
					play-on-hover
					:autoplay="isHoveringName === taskRunnerClass.name"
				/>
				<QTooltip v-if="collapsed">{{ taskRunnerClass.name }}</QTooltip>
			</div>
			<div v-if="!collapsed" class="flex-x ml-2 flex-grow max-w-full overflow-hidden">
				<div class="flex-grow max-w-full overflow-hidden">
					<div class="node-item-title whitespace-nowrap">{{ taskRunnerClass.name }}</div>
					<QTooltip v-if="taskRunnerClass.description" class="text-sm text-slate-300 bg-slate-700 p-3 rounded">
						{{ taskRunnerClass.description }}
					</QTooltip>
				</div>
			</div>
		</LabelPillWidget>
	</div>
</template>

<script setup lang="ts">
import { TaskRunnerClasses } from "@/components/Modules/TaskDefinitions/TaskRunners";
import { onDragStart } from "@/components/Modules/WorkflowCanvas/dragNDrop";
import { addWorkflowNode } from "@/components/Modules/WorkflowDefinitions/store";
import { WorkflowDefinition } from "@/types";
import { LabelPillWidget } from "quasar-ui-danx";
import { ref } from "vue";

defineEmits(["refresh"]);
defineProps<{
	workflowDefinition: WorkflowDefinition;
	collapsed?: boolean;
}>();

const isHoveringName = ref("");
</script>
