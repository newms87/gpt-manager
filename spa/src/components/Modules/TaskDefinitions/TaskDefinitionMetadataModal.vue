<template>
	<InfoDialog
		v-if="visible"
		title="Task Definition Metadata"
		color="sky"
		:hide-cancel="true"
		ok-label="Close"
		content-class="!max-w-4xl"
		@close="$emit('close')"
	>
		<div class="metadata-modal-container">
			<div class="bg-sky-50 rounded-lg p-4 mb-4">
				<h4 class="text-lg font-semibold text-gray-900 mb-2">
					{{ taskDefinition?.name }}
				</h4>
				<div class="text-sm text-gray-600">
					Task Runner: {{ taskDefinition?.task_runner_name }}
				</div>
			</div>

			<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
				<div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex items-center justify-between">
					<h4 class="text-base font-medium text-gray-900">
						Metadata
					</h4>
					<ActionButton
						v-if="hasMetadata"
						type="trash"
						color="red"
						size="sm"
						label="Clear"
						:action="updateAction"
						:target="taskDefinition"
						:input="{meta: null}"
						tooltip="Clear all metadata"
						@success="$emit('close')"
					/>
				</div>

				<div class="p-4">
					<MarkdownEditor
						v-if="hasMetadata"
						:model-value="metadataYaml"
						:readonly="true"
						format="yaml"
						editor-class="h-96 bg-slate-900 text-slate-100 rounded"
					/>
					<div v-else class="p-8 text-center text-gray-500">
						No metadata available for this task definition
					</div>
				</div>
			</div>
		</div>
	</InfoDialog>
</template>

<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor.vue";
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions/config";
import { TaskDefinition } from "@/types";
import { ActionButton, InfoDialog } from "quasar-ui-danx";
import { stringify as yamlStringify } from "yaml";
import { computed } from "vue";

interface Props {
	taskDefinition?: TaskDefinition | null;
	visible?: boolean;
}

const props = defineProps<Props>();

defineEmits<{
	(e: "close"): void;
}>();

const updateAction = dxTaskDefinition.getAction("update");

const hasMetadata = computed(() => {
	return props.taskDefinition?.meta && Object.keys(props.taskDefinition.meta).length > 0;
});

const metadataYaml = computed(() => {
	if (!props.taskDefinition?.meta) return "";
	return yamlStringify(props.taskDefinition.meta, { indent: 2 });
});
</script>
