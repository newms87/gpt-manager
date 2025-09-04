<template>
	<BaseTaskRunnerNode :workflow-node="workflowNode" :task-run="taskRun" :lottie="DeliveryBoyLottie">
		<template #ports>
			<NodePortsWidget
				:task-run="taskRun"
				:source-edges="sourceEdges"
				:target-edges="targetEdges"
				:source-ports="[]"
			/>
		</template>
	</BaseTaskRunnerNode>
</template>
<script setup lang="ts">
import { DeliveryBoyLottie } from "@/assets/dotlottie";
import NodePortsWidget from "@/components/Modules/WorkflowCanvas/NodePortsWidget";
import { useWorkflowNode } from "@/components/Modules/WorkflowCanvas/useWorkflowNode";
import { TaskRun, WorkflowNode } from "@/types";
import { toRefs } from "vue";
import BaseTaskRunnerNode from "./BaseTaskRunnerNode";

const props = defineProps<{
	workflowNode: WorkflowNode;
	taskRun?: TaskRun;
}>();

const { workflowNode, taskRun } = toRefs(props);
const { sourceEdges, targetEdges } = useWorkflowNode(workflowNode, taskRun);
</script>