<template>
	<div class="relative">
		<slot />
	</div>
</template>
<script setup lang="ts">
import { TaskDefinition } from "@/types";

defineProps<{
	taskDefinition: TaskDefinition;
}>();
</script>
