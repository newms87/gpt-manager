<template>
	<div class="flex items-center justify-center flex-nowrap relative">
		<slot>
			<BaseNodeIcon class="w-[4.4rem]" />
		</slot>
	</div>
</template>
<script setup lang="ts">
import { TaskRun, WorkflowNode } from "@/types";
import { FaSolidSquareShareNodes as BaseNodeIcon } from "danx-icon";

defineProps<{
	workflowNode: WorkflowNode;
	taskRun?: TaskRun;
}>();
</script>
