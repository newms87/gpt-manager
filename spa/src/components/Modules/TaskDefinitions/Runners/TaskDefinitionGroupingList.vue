<template>
	<div class="bg-sky-950 p-4 rounded">
		<div class="flex">
			<div class="pr-4 py-4">Grouping Mode:</div>
			<QTabs
				v-model="config.mode"
				class="tab-buttons border-sky-900"
				indicator-color="sky-900"
			>
				<QTab name="Append" label="Append" />
				<QTab name="Merge" label="Merge" />
				<QTab name="Overwrite" label="Overwrite" />
				<QTab name="Split" label="Split" />
			</QTabs>
		</div>
	</div>
</template>
<script setup lang="ts">
import { TaskDefinition } from "@/types";
import { ref } from "vue";

defineProps<{
	taskDefinition: TaskDefinition,
}>();

const config = ref({
	mode: "Append"
});
</script>
