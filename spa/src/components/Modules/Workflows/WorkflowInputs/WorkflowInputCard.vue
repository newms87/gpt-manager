<template>
	<div>
		<div class="flex items-center flex-nowrap space-x-4 h-20">
			<ShowHideButton v-if="!readonly" v-model="isEditing" :show-icon="EditIcon" class="bg-slate-700" />
			<QBtn v-if="readonly" class="rounded-full bg-sky-800 text-sky-200 px-4 py-2" @click="$emit('select')">{{
					workflowInput.name
				}}
			</QBtn>
			<div v-else class="rounded-full bg-sky-800 text-sky-200 px-4 py-2">
				<EditableDiv
					:readonly="readonly"
					color="sky-800"
					:model-value="workflowInput.name"
					placeholder="Enter Name..."
					@update:model-value="name => updateAction.trigger(workflowInput, { name })"
				/>
			</div>
			<EditableDiv
				:readonly="readonly"
				color="slate-700"
				:model-value="workflowInput.description"
				placeholder="Enter Description..."
				@update:model-value="description => updateAction.trigger(workflowInput, { description })"
			/>
			<MultiFileField
				:readonly="readonly"
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
		<div v-if="isEditing && !readonly" class="mt-4">
			<MarkdownEditor
				:model-value="workflowInput.content"
				:max-length="60000"
				@update:model-value="content => debouncedUpdateAction.trigger(workflowInput, { content })"
			/>
			<SaveStateIndicator
				class="mt-1"
				:saving="debouncedUpdateAction.isApplying"
				:saved-at="workflowInput.updated_at"
			/>
		</div>
	</div>
</template>
<script setup lang="ts">
import { MarkdownEditor } from "@/components/MarkdownEditor";
import { dxWorkflowInput } from "@/components/Modules/Workflows/WorkflowInputs/config";
import { WorkflowInput } from "@/types";
import { FaSolidPencil as EditIcon } from "danx-icon";
import { EditableDiv, MultiFileField, SaveStateIndicator, ShowHideButton } from "quasar-ui-danx";
import { ref } from "vue";

defineEmits(["select"]);
defineProps<{
	workflowInput: WorkflowInput;
	readonly?: boolean;
}>();

const updateAction = dxWorkflowInput.getAction("update");
const debouncedUpdateAction = dxWorkflowInput.getAction("update", { debounce: 500 });
const isEditing = ref(false);
</script>
