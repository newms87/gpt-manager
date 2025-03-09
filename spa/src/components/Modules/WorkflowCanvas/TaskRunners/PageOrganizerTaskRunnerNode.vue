<template>
	<BaseTaskRunnerNode :config="config">
		<PageOrganizerLottie class="w-[10rem]" version="original" :autoplay="autoplay" />
	</BaseTaskRunnerNode>
</template>
<script setup lang="ts">
import { PageOrganizerLottie } from "@/assets/dotlottie";
import BaseTaskRunnerNode from "@/components/Modules/WorkflowCanvas/TaskRunners/BaseTaskRunnerNode";
import { AnyObject } from "quasar-ui-danx";

defineProps<{
	config?: AnyObject;
	autoplay?: boolean;
}>();
</script>
