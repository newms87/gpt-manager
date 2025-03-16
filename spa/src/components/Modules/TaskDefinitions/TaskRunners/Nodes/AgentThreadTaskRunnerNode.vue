<template>
	<BaseTaskRunnerNode :config="config">
		<AgentThreadRunnerLottie class="w-[12rem]" :autoplay="autoplay" :finished="finished" />
	</BaseTaskRunnerNode>
</template>
<script setup lang="ts">
import { AgentThreadRunnerLottie } from "@/assets/dotlottie";
import { AnyObject } from "quasar-ui-danx";
import BaseTaskRunnerNode from "./BaseTaskRunnerNode";

defineProps<{
	config?: AnyObject;
	autoplay?: boolean;
	finished?: boolean;
}>();
</script>
