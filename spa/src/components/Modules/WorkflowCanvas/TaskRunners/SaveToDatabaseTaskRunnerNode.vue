<template>
	<BaseTaskRunnerNode :config="config">
		<SaveToDbLottie class="w-[14rem] mt-[-.5rem]" :autoplay="autoplay" :finished="finished" />
	</BaseTaskRunnerNode>
</template>
<script setup lang="ts">
import { SaveToDbLottie } from "@/assets/dotlottie";
import BaseTaskRunnerNode from "@/components/Modules/WorkflowCanvas/TaskRunners/BaseTaskRunnerNode";
import { AnyObject } from "quasar-ui-danx";

defineProps<{
	config?: AnyObject;
	autoplay?: boolean;
	finished?: boolean;
}>();
</script>
