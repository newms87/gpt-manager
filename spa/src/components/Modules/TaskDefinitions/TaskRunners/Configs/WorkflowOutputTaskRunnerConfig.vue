<template>
	<BaseTaskRunnerConfig :task-definition="taskDefinition">
		<QSeparator class="bg-slate-400 my-4" />

		<div class="flex flex-col gap-6">
			<!-- Toggle for enabling artifact naming -->
			<div class="flex items-center gap-4">
				<QToggle
					v-model="enableNaming"
					color="primary"
					@update:model-value="onUpdateToggle"
				/>
				<div>
					<div class="font-bold">Enable Artifact Naming</div>
					<div class="text-sm text-slate-400">
						Automatically generate descriptive names for output artifacts using AI
					</div>
				</div>
			</div>

			<!-- Instructions field (only shown when enabled) -->
			<div v-if="enableNaming">
				<MarkdownEditor
					v-model="namingInstructions"
					label="Artifact Naming Instructions"
					placeholder="Enter custom instructions for naming artifacts..."
					:max-length="1000"
					editor-class="min-h-[120px] bg-slate-800"
					@update:model-value="debounceChange"
				/>
				<div class="text-xs text-slate-400 mt-1">
					Provide specific guidance on how to name artifacts (optional). Example: "Use patient name and date format"
				</div>
			</div>

			<!-- Info box -->
			<div class="bg-slate-700 border border-slate-600 rounded-lg p-4">
				<div class="flex items-center gap-2 mb-2">
					<FaSolidCircleInfo class="w-4 h-4 text-blue-400" />
					<span class="text-sm font-medium text-slate-300">How Artifact Naming Works</span>
				</div>
				<ul class="text-xs text-slate-400 space-y-1">
					<li>• AI analyzes artifact content to generate descriptive names</li>
					<li>• Custom instructions help tailor names to your specific needs</li>
					<li>• Disabled by default to preserve original artifact names</li>
					<li>• Enable when you need consistent, professional naming across outputs</li>
				</ul>
			</div>
		</div>
	</BaseTaskRunnerConfig>
</template>

<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor.vue";
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { TaskDefinition } from "@/types";
import { useDebounceFn } from "@vueuse/core";
import { FaSolidCircleInfo } from "danx-icon";
import { QToggle } from "quasar";
import { computed, ref, watch } from "vue";
import BaseTaskRunnerConfig from "./BaseTaskRunnerConfig.vue";

const props = defineProps<{
	taskDefinition: TaskDefinition;
}>();

const config = computed(() => props.taskDefinition.task_runner_config || {});
const enableNaming = ref(config.value.enable_artifact_naming || false);
const namingInstructions = ref(config.value.artifact_naming_instructions || '');

// Watch for external changes to the config
watch(() => props.taskDefinition.task_runner_config?.enable_artifact_naming, (value) => {
	enableNaming.value = value || false;
});

watch(() => props.taskDefinition.task_runner_config?.artifact_naming_instructions, (value) => {
	namingInstructions.value = value || '';
});

const updateTaskDefinitionAction = dxTaskDefinition.getAction("update");

function onUpdateToggle() {
	updateTaskDefinitionAction.trigger(props.taskDefinition, {
		task_runner_config: {
			...config.value,
			enable_artifact_naming: enableNaming.value
		}
	});
}

const debounceChange = useDebounceFn(() => {
	updateTaskDefinitionAction.trigger(props.taskDefinition, {
		task_runner_config: {
			...config.value,
			enable_artifact_naming: enableNaming.value,
			artifact_naming_instructions: namingInstructions.value
		}
	});
}, 500);
</script>
