<template>
	<div class="task-definition-prompt-field">
		<MarkdownEditor
			v-model="promptValue"
			:label="label"
			:placeholder="placeholder"
			:max-length="maxLength"
			@update:model-value="debouncedUpdate"
		/>
	</div>
</template>

<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { TaskDefinition } from "@/types";
import { useDebounceFn } from "@vueuse/core";
import { ref, watch } from "vue";

const props = withDefaults(defineProps<{
	taskDefinition: TaskDefinition;
	label?: string;
	placeholder?: string;
	maxLength?: number;
}>(), {
	label: "Prompt",
	placeholder: "Enter your prompt instructions...",
	maxLength: 64000
});

const promptValue = ref(props.taskDefinition.prompt || "");

// Watch for external changes to taskDefinition.prompt
watch(() => props.taskDefinition.prompt, (newValue) => {
	if (newValue !== promptValue.value) {
		promptValue.value = newValue || "";
	}
});

const updateTaskDefinitionAction = dxTaskDefinition.getAction("update");

const debouncedUpdate = useDebounceFn(() => {
	updateTaskDefinitionAction.trigger(props.taskDefinition, {
		prompt: promptValue.value
	});
}, 500);
</script>
