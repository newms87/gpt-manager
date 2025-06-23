<template>
	<div v-if="showDeduplicationOption" class="deduplication-config-field">
		<QSeparator class="bg-slate-400 my-4" />
		<div class="flex items-center gap-4">
			<QToggle
				v-model="deduplicateNames"
				color="primary"
				@update:model-value="onUpdate"
			/>
			<div>
				<div class="font-bold">Deduplicate record names</div>
				<div class="text-sm text-slate-400">
					Normalize similar names across parallel processes when creating new records (e.g., "CNCC Chiropractic" → "Chiropractic Natural Care Center")
				</div>
			</div>
		</div>
	</div>
</template>
<script setup lang="ts">
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { TaskDefinition } from "@/types";
import { computed, ref, watch } from "vue";

const props = defineProps<{
	taskDefinition: TaskDefinition;
}>();

const showDeduplicationOption = computed(() => props.taskDefinition.response_format === "json_schema");
const deduplicateNames = ref(props.taskDefinition.task_runner_config?.deduplicate_names || false);

watch(() => props.taskDefinition.task_runner_config?.deduplicate_names, (value) => {
	deduplicateNames.value = value || false;
});

const updateTaskDefinitionAction = dxTaskDefinition.getAction("update");

function onUpdate() {
	updateTaskDefinitionAction.trigger(props.taskDefinition, {
		task_runner_config: {
			...props.taskDefinition.task_runner_config,
			deduplicate_names: deduplicateNames.value
		}
	});
}
</script>