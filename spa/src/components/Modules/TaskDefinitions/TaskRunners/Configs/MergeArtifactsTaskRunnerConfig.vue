<template>
	<BaseTaskRunnerConfig :task-definition="taskDefinition">
		<div class="font-bold mb-2 mt-8 text-lg">Define a <b>json content</b> fragment</div>
		<FragmentSelectorConfigField v-model="jsonContentFragmentSelector" @update:model-value="debounceChange" />
		<div class="font-bold mb-2 mt-8 text-lg">Define a <b>meta</b> fragment</div>
		<FragmentSelectorConfigField v-model="metaFragmentSelector" @update:model-value="debounceChange" />
	</BaseTaskRunnerConfig>
</template>
<script setup lang="ts">
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { TaskDefinition } from "@/types";
import { useDebounceFn } from "@vueuse/core";
import { computed, ref } from "vue";
import BaseTaskRunnerConfig from "./BaseTaskRunnerConfig";
import { FragmentSelectorConfigField } from "./Fields";

const props = defineProps<{
	taskDefinition: TaskDefinition;
}>();

export interface MergeArtifactsTaskRunnerConfig {
	json_content_fragment_selector: string;
	meta_fragment_selector: string;
}

const config = computed(() => (props.taskDefinition.task_runner_config || {}) as MergeArtifactsTaskRunnerConfig);
const jsonContentFragmentSelector = ref(config.value.json_content_fragment_selector);
const metaFragmentSelector = ref(config.value.meta_fragment_selector);

const updateTaskDefinitionAction = dxTaskDefinition.getAction("update");

const debounceChange = useDebounceFn(() => {
	updateTaskDefinitionAction.trigger(props.taskDefinition, {
		task_runner_config: {
			...config.value,
			json_content_fragment_selector: jsonContentFragmentSelector.value,
			meta_fragment_selector: metaFragmentSelector.value
		}
	});
}, 500);
</script>
