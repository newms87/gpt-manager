<template>
	<BaseTaskRunnerConfig :task-definition="taskDefinition">
		<div class="p-4">
			<div class="text-xl font-medium mb-2">File Organization Configuration</div>
			<div class="text-sm text-slate-600 mb-4">
				This task runner organizes files intelligently by comparing them in groups and suggesting optimal folder
				structures based on their content and relationships.
			</div>

			<!-- Organization Instructions -->
			<TaskDefinitionPromptField
				:task-definition="taskDefinition"
				label="Organization Instructions"
				placeholder="Describe how files should be grouped and organized. For example: 'Group medical records by patient name' or 'Organize invoices by month and vendor'."
				class="mb-6"
			/>

			<!-- Comparison Window Size Field -->
			<NumberField
				v-model="comparisonWindowSize"
				label="Comparison Window Size"
				placeholder="Enter window size..."
				:min="2"
				:max="5"
				prepend-label
				class="mb-2"
				@update:model-value="debounceChange"
			/>
			<div class="text-xs text-gray-500 mb-6 ml-1">
				<p>Number of files to compare at once (2-5):</p>
				<ul class="list-disc pl-5 mt-1">
					<li><b>Smaller window (2-3):</b> Higher page-by-page accuracy, but slower and more expensive</li>
					<li><b>Larger window (4-5):</b> Better broader context, faster, and cheaper, but may miss accuracy in certain scenarios</li>
					<li class="mt-1 text-gray-600"><b>Recommended:</b> Use larger window size (4-5) for most use cases</li>
				</ul>
			</div>
		</div>
	</BaseTaskRunnerConfig>
</template>

<script setup lang="ts">
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { TaskDefinition } from "@/types";
import { useDebounceFn } from "@vueuse/core";
import { NumberField } from "quasar-ui-danx";
import { computed, ref } from "vue";
import BaseTaskRunnerConfig from "./BaseTaskRunnerConfig";
import { TaskDefinitionPromptField } from "./Fields";

export interface FileOrganizationTaskRunnerConfig {
	comparison_window_size: number;
}

const props = defineProps<{
	taskDefinition: TaskDefinition;
}>();

const config = computed(() => (props.taskDefinition.task_runner_config || {}) as FileOrganizationTaskRunnerConfig);
const comparisonWindowSize = ref(config.value.comparison_window_size ?? 3);

const updateTaskDefinitionAction = dxTaskDefinition.getAction("update");

const debounceChange = useDebounceFn(() => {
	updateTaskDefinitionAction.trigger(props.taskDefinition, {
		task_runner_config: {
			...config.value,
			comparison_window_size: Number(comparisonWindowSize.value) || 3
		}
	});
}, 500);
</script>
