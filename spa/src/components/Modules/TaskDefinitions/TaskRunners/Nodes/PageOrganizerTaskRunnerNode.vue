<template>
	<BaseTaskRunnerNode :config="config">
		<PageOrganizerLottie class="w-[10rem]" :autoplay="autoplay" :finished="finished" />
	</BaseTaskRunnerNode>
</template>
<script setup lang="ts">
import { PageOrganizerLottie } from "@/assets/dotlottie";
import { AnyObject } from "quasar-ui-danx";
import BaseTaskRunnerNode from "./BaseTaskRunnerNode";

defineProps<{
	config?: AnyObject;
	autoplay?: boolean;
	finished?: boolean;
}>();
</script>
