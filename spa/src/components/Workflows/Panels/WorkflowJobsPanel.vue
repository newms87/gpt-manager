<template>
	<div class="p-6">
		Jobs
	</div>
</template>
<script setup lang="ts">
import { Workflow } from "@/components/Workflows/workflows";

defineProps<{
	workflow: Workflow,
}>();
</script>
