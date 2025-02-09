<template>
	<div>
		<div class="flex items-center flex-nowrap gap-x-4">
			<h6>Example Response</h6>
			<ActionButton
				:action="generateExampleAction"
				:target="promptSchema"
				class="text-base px-6"
				:class="{'bg-yellow-800': !!promptSchema.response_example, 'bg-sky-800': !promptSchema.response_example}"
				:icon="GenerateExampleIcon"
				icon-class="w-5"
				:label="promptSchema.response_example ? 'Regenerate Example' : 'Generate Example'"
				:loading="generateExampleAction.isApplying"
				@click="generateExampleAction.trigger(promptSchema)"
			/>
		</div>
		<MarkdownEditor
			:model-value="promptSchema.response_example || ''"
			sync-model-changes
			:format="promptSchema.schema_format"
			@update:model-value="updateDebouncedAction.trigger(promptSchema, { response_example: $event })"
		/>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import { dxPromptSchema } from "@/components/Modules/Schemas/Schemas";
import { ActionButton } from "@/components/Shared";
import { SchemaDefinition } from "@/types";
import { FaSolidRobot as GenerateExampleIcon } from "danx-icon";

defineProps<{
	promptSchema: SchemaDefinition,
}>();

const updateDebouncedAction = dxPromptSchema.getAction("update-debounced");
const generateExampleAction = dxPromptSchema.getAction("generate-example");
</script>
