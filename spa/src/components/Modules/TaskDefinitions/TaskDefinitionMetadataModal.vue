<template>
	<InfoDialog
		v-if="visible"
		title="Task Definition Metadata"
		color="sky"
		:hide-cancel="true"
		ok-label="Close"
		content-class="w-[80vw] h-[80vh] max-w-none"
		@close="$emit('close')"
	>
		<div class="metadata-modal-container h-full flex flex-col">
			<div class="bg-sky-50 rounded-lg p-4 mb-4 flex-shrink-0">
				<h4 class="text-lg font-semibold text-gray-900 mb-2">
					{{ taskDefinition?.name }}
				</h4>
				<div class="text-sm text-gray-600">
					Task Runner: {{ taskDefinition?.task_runner_name }}
				</div>
			</div>

			<div class="bg-white rounded-lg border border-gray-200 overflow-hidden flex-1 flex flex-col min-h-0">
				<div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex items-center justify-between flex-shrink-0">
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

				<div class="p-4 flex-1 min-h-0 flex flex-col">
					<CodeViewer
						v-if="hasMetadata"
						:model-value="taskDefinition?.meta"
						format="yaml"
						can-edit
						@update:model-value="onMetadataUpdate"
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
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions/config";
import { TaskDefinition } from "@/types";
import { ActionButton, CodeViewer, InfoDialog } from "quasar-ui-danx";
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

async function onMetadataUpdate(meta: object | string | null) {
	if (props.taskDefinition && typeof meta === "object") {
		await updateAction.trigger(props.taskDefinition, { meta });
	}
}
</script>
