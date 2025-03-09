<template>
	<BaseTaskRunnerNode :config="config">
		<AgentThreadRunnerLottie class="w-[10rem]" :autoplay="autoplay" />
	</BaseTaskRunnerNode>
</template>
<script setup lang="ts">
import { AgentThreadRunnerLottie } from "@/assets/dotlottie";
import BaseTaskRunnerNode from "@/components/Modules/WorkflowCanvas/TaskRunners/BaseTaskRunnerNode";
import { AnyObject } from "quasar-ui-danx";

defineProps<{
	config?: AnyObject;
	autoplay?: boolean;
}>();
</script>
