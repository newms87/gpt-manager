<template>
	<div>
		<div class="flex-x gap-x-4">
			<h6>Example Response</h6>
			<ActionButton
				:action="generateExampleAction"
				:target="schemaDefinition"
				class="text-base px-6"
				:class="{'bg-yellow-800': !!schemaDefinition.response_example, 'bg-sky-800': !schemaDefinition.response_example}"
				:icon="GenerateExampleIcon"
				icon-class="w-5"
				:label="schemaDefinition.response_example ? 'Regenerate Example' : 'Generate Example'"
				:loading="generateExampleAction.isApplying"
				@click="generateExampleAction.trigger(schemaDefinition)"
			/>
		</div>
		<CodeViewer
			:model-value="schemaDefinition.response_example || ''"
			can-edit
			:format="schemaDefinition.schema_format"
			@update:model-value="updateDebouncedAction.trigger(schemaDefinition, { response_example: $event })"
		/>
	</div>
</template>
<script setup lang="ts">
import { dxSchemaDefinition } from "@/components/Modules/SchemaEditor/config";
import { CodeViewer } from "quasar-ui-danx";
import { SchemaDefinition } from "@/types";
import { FaSolidRobot as GenerateExampleIcon } from "danx-icon";
import { ActionButton } from "quasar-ui-danx";

defineProps<{
	schemaDefinition: SchemaDefinition,
}>();

const updateDebouncedAction = dxSchemaDefinition.getAction("update-debounced");
const generateExampleAction = dxSchemaDefinition.getAction("generate-example");
</script>
