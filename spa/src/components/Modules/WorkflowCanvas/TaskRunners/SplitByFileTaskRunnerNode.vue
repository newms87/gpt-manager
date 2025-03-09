<template>
	<BaseTaskRunnerNode :config="config">
		<SplitByFileLottie class="w-[10rem]" :autoplay="autoplay" />
	</BaseTaskRunnerNode>
</template>
<script setup lang="ts">
import SplitByFileLottie from "@/assets/dotlottie/SplitByFileLottie";
import BaseTaskRunnerNode from "@/components/Modules/WorkflowCanvas/TaskRunners/BaseTaskRunnerNode";
import { AnyObject } from "quasar-ui-danx";

defineProps<{
	config?: AnyObject;
	autoplay?: boolean;
}>();
</script>
