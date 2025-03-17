<template>
	<div class="flex items-center justify-center flex-nowrap relative h-full w-full">
		<slot>
			<BaseNodeIcon class="w-[4.4rem]" />
		</slot>

		<slot name="ports">
			<NodePortsWidget
				:task-run="taskRun"
				:source-edges="sourceEdges"
				:target-edges="targetEdges"
			/>
		</slot>
	</div>
</template>
<script setup lang="ts">
import NodePortsWidget from "@/components/Modules/WorkflowCanvas/NodePortsWidget";
import { useWorkflowNode } from "@/components/Modules/WorkflowCanvas/useWorkflowNode";
import { TaskRun, WorkflowNode } from "@/types";
import { FaSolidSquareShareNodes as BaseNodeIcon } from "danx-icon";
import { toRefs } from "vue";

const props = defineProps<{
	workflowNode: WorkflowNode;
	taskRun?: TaskRun;
}>();

const { workflowNode, taskRun } = toRefs(props);
const { sourceEdges, targetEdges } = useWorkflowNode(workflowNode, taskRun);
</script>
