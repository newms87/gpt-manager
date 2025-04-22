<template>
	<BaseTaskRunnerConfig :task-definition="taskDefinition">
		<div class="p-2 mt-4">
			<div class="text-xl font-medium mb-2">Artifact Level Projection Configuration</div>
			<div class="text-sm text-slate-600 mb-4">
				Configure projection between different artifact levels. You can project content from source level artifacts
				to target level artifacts while respecting hierarchical relationships.
			</div>

			<div class="mb-6">
				<ArtifactLevelsField
					mode="input"
					:levels="config.source_levels"
					@update:levels="updateSourceLevels"
				/>
			</div>

			<div class="mb-6">
				<div class="font-bold mb-2">Target Levels</div>
				<ArtifactLevelsField
					mode="input"
					:levels="config.target_levels"
					@update:levels="updateTargetLevels"
				/>
			</div>

			<div class="mb-4">
				<TextField
					v-model="config.text_separator"
					label="Text Separator"
					placeholder="\n---\n"
				/>
			</div>

			<div class="mb-4">
				<TextField
					v-model="config.text_prefix"
					label="Text Prefix"
					placeholder="From source: "
				/>
			</div>
		</div>
	</BaseTaskRunnerConfig>
</template>

<script setup lang="ts">
import { TaskDefinition } from "@/types";
import { PropType, computed } from "vue";
import BaseTaskRunnerConfig from "./BaseTaskRunnerConfig.vue";
import ArtifactLevelsField from "@/components/Modules/TaskDefinitions/TaskRunners/Configs/Fields/ArtifactLevelsField.vue";
import { TextField } from "quasar-ui-danx";

const props = defineProps({
	taskDefinition: {
		type: Object as PropType<TaskDefinition>,
		required: true
	}
});

// Default config values
const defaultConfig = {
	source_levels: [0],
	target_levels: [1],
	text_separator: "\n---\n",
	text_prefix: "From source: "
};

// Get the current config or use defaults
const config = computed({
	get() {
		const currentConfig = props.taskDefinition.task_runner_config || {};
		return {
			source_levels: currentConfig.source_levels || defaultConfig.source_levels,
			target_levels: currentConfig.target_levels || defaultConfig.target_levels,
			text_separator: currentConfig.text_separator || defaultConfig.text_separator,
			text_prefix: currentConfig.text_prefix || defaultConfig.text_prefix
		};
	},
	set(newValue) {
		props.taskDefinition.task_runner_config = newValue;
	}
});

// Update methods
function updateSourceLevels(levels) {
	config.value = {
		...config.value,
		source_levels: levels
	};
}

function updateTargetLevels(levels) {
	config.value = {
		...config.value,
		target_levels: levels
	};
}
</script>
