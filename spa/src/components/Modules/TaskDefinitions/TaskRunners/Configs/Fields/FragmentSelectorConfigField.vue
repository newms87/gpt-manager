<template>
	<div>
		<div class="font-bold mb-2 mt-8 text-lg">Define a fragment to merge artifacts</div>
		<MarkdownEditor
			v-model="input.fragment_selector"
			format="yaml"
			editor-class=""
			@update:model-value="debounceChange"
		/>
	</div>
</template>
<script setup lang="ts">
import { MarkdownEditor } from "@/components/MarkdownEditor";
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { TaskDefinition } from "@/types";
import { useDebounceFn } from "@vueuse/core";
import { ref } from "vue";

const props = defineProps<{
	taskDefinition: TaskDefinition;
}>();

export interface FragmentSelectorConfig {
	fragment_selector: string;
}

const input = ref<FragmentSelectorConfig>({
	fragment_selector: props.taskDefinition.task_runner_config?.fragment_selector || {
		type: "object",
		children: { name: { type: "string" } }
	}
});

const updateTaskDefinitionAction = dxTaskDefinition.getAction("update");

const debounceChange = useDebounceFn(() => {
	updateTaskDefinitionAction.trigger(props.taskDefinition, {
		task_runner_config: input.value
	});
}, 500);
</script>
