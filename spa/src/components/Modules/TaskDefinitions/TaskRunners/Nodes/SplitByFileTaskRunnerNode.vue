<template>
	<BaseTaskRunnerNode :config="config">
		<SplitByFileLottie class="w-[12rem]" :autoplay="autoplay" :finished="finished" />
	</BaseTaskRunnerNode>
</template>
<script setup lang="ts">
import { SplitByFileLottie } from "@/assets/dotlottie";
import { AnyObject } from "quasar-ui-danx";
import BaseTaskRunnerNode from "./BaseTaskRunnerNode";

defineProps<{
	config?: AnyObject;
	autoplay?: boolean;
	finished?: boolean;
}>();
</script>
