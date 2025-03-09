<template>
	<BaseTaskRunnerNode :config="config">
		<SaveToDbLottie class="w-[10rem]" :autoplay="autoplay" />
	</BaseTaskRunnerNode>
</template>
<script setup lang="ts">
import SaveToDbLottie from "@/assets/dotlottie/SaveToDbLottie";
import BaseTaskRunnerNode from "@/components/Modules/WorkflowCanvas/TaskRunners/BaseTaskRunnerNode";
import { AnyObject } from "quasar-ui-danx";

defineProps<{
	config?: AnyObject;
	autoplay?: boolean;
}>();
</script>
