<template>
	<BaseTaskRunnerNode :config="config">
		<ImageToTextLottie class="w-[10rem]" :autoplay="autoplay" :finished="finished" />
	</BaseTaskRunnerNode>
</template>
<script setup lang="ts">
import { ImageToTextLottie } from "@/assets/dotlottie";
import BaseTaskRunnerNode from "@/components/Modules/WorkflowCanvas/TaskRunners/BaseTaskRunnerNode";
import { AnyObject } from "quasar-ui-danx";

defineProps<{
	config?: AnyObject;
	autoplay?: boolean;
	finished?: boolean;
}>();
</script>
