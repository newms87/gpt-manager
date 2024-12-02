<template>
	<div>
		<div class="flex items-center flex-nowrap space-x-4 h-20">
			<ShowHideButton v-model="isEditing" :show-icon="EditIcon" class="bg-slate-700" />
			<div class="rounded-full bg-sky-800 text-sky-200 px-4 py-2">
				<EditableDiv
					color="sky-800"
					:model-value="workflowInput.name"
					placeholder="Enter Name..."
					@update:model-value="name => updateAction.trigger(workflowInput, { name })"
				/>
			</div>
			<EditableDiv
				color="slate-700"
				:model-value="workflowInput.description"
				placeholder="Enter Description..."
				@update:model-value="description => updateAction.trigger(workflowInput, { description })"
			/>
			<MultiFileField
				:disable="!isEditing"
				:model-value="workflowInput.files"
				:width="70"
				:height="60"
				add-icon-class="w-5"
				file-preview-class="rounded-lg"
				file-preview-btn-size="xs"
				@update:model-value="files => updateAction.trigger(workflowInput, { files })"
			/>
		</div>
		<div v-if="isEditing" class="mt-4">
			<MarkdownEditor
				:model-value="workflowInput.content"
				@update:model-value="content => updateAction.trigger(workflowInput, { content })"
			/>
		</div>
	</div>
</template>
<script setup lang="ts">
import { MarkdownEditor } from "@/components/MarkdownEditor";
import { dxWorkflowInput } from "@/components/Modules/Workflows/WorkflowInputs/config";
import { WorkflowInput } from "@/types";
import { FaSolidPencil as EditIcon } from "danx-icon";
import { EditableDiv, MultiFileField, ShowHideButton } from "quasar-ui-danx";
import { ref } from "vue";

defineProps<{
	workflowInput: WorkflowInput;
}>();

const updateAction = dxWorkflowInput.getAction("update");
const isEditing = ref(false);
</script>
