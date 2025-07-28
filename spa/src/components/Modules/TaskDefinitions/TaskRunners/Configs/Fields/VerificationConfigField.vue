<template>
	<div v-if="showVerificationOption" class="verification-config-field">
		<QSeparator class="bg-slate-400 my-4" />
		<div class="space-y-4">
			<div class="font-bold text-lg">Classification Verification</div>
			<div class="text-sm text-slate-400">
				Select properties to verify for classification accuracy. The system will check classifications by comparing sequential pages and providing context when differences are detected.
			</div>

			<div class="space-y-3">
				<div class="font-semibold">Properties to Verify:</div>
				<SelectField
					:model-value="verifyProperties"
					multiple
					:options="propertyOptions"
					placeholder="Select properties to verify..."
					@update="onUpdateVerify"
				/>
			</div>

			<div v-if="verifyProperties.length > 0" class="text-xs text-blue-300 bg-blue-950 p-3 rounded">
				<div class="font-semibold mb-1">Verification Process:</div>
				<ul class="list-disc list-inside space-y-1">
					<li>System detects classification differences between sequential pages</li>
					<li>Provides context window (previous 2, current, next 1 pages) to classifier</li>
					<li>AI agent verifies and corrects classifications with better context</li>
					<li>Only creates verification processes when outliers are detected</li>
				</ul>
			</div>
		</div>
	</div>
</template>
<script setup lang="ts">
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { TaskDefinition } from "@/types";
import { SelectField } from "quasar-ui-danx";
import { computed, ref, watch } from "vue";

const props = defineProps<{
	taskDefinition: TaskDefinition;
}>();

// Only show verification option for JSON schema responses with selected schema
const showVerificationOption = computed(() => 
	props.taskDefinition.response_format === "json_schema" && 
	props.taskDefinition.schemaDefinition?.schema
);

// Extract property options from the schema
const propertyOptions = computed(() => {
	if (!props.taskDefinition.schemaDefinition?.schema?.properties) {
		return [];
	}

	const schemaProperties = props.taskDefinition.schemaDefinition.schema.properties;
	return Object.keys(schemaProperties).map(key => ({
		label: schemaProperties[key].title || key,
		value: key,
		description: schemaProperties[key].description
	})).sort((a, b) => a.label.localeCompare(b.label));
});

// Get current verify properties from task runner config
const verifyProperties = ref<string[]>(props.taskDefinition.task_runner_config?.verify || []);

// Watch for changes in the task definition
watch(() => props.taskDefinition.task_runner_config?.verify, (value) => {
	verifyProperties.value = value || [];
});

const updateTaskDefinitionAction = dxTaskDefinition.getAction("update");

function onUpdateVerify(selectedProperties: string[]) {
	verifyProperties.value = selectedProperties;
	
	// Remove verify key if no properties selected
	const updatedConfig = { ...props.taskDefinition.task_runner_config };
	if (selectedProperties.length === 0) {
		delete updatedConfig.verify;
	} else {
		updatedConfig.verify = selectedProperties;
	}

	updateTaskDefinitionAction.trigger(props.taskDefinition, {
		task_runner_config: updatedConfig
	});
}
</script>