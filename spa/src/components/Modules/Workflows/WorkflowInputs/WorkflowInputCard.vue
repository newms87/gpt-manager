<template>
	<div>
		<div class="flex items-center flex-nowrap h-20">
			<div>{{ workflowInput.name }}</div>
			<div v-if="workflowInput.description" class="ml-4">
				{{ workflowInput.description }}
			</div>
			<div v-if="workflowInput.thumb" class="ml-4">
				<FilePreview :file="workflowInput.thumb" class="w-20 h-20" :related-files="workflowInput.files" />
			</div>
			<ShowHideButton v-model="isEditing" class="ml-8 bg-slate-700" label="Edit" :show-icon="EditIcon" />
		</div>
		<div v-if="isEditing" class="w-full mt-4">
			<WorkflowInputInputPanel :workflow-input="workflowInput" />
		</div>
	</div>
</template>
<script setup lang="ts">
import { WorkflowInputInputPanel } from "@/components/Modules/Workflows/WorkflowInputs/Panels";
import { ShowHideButton } from "@/components/Shared";
import { WorkflowInput } from "@/types";
import { FaSolidPencil as EditIcon } from "danx-icon";
import { FilePreview } from "quasar-ui-danx";
import { ref } from "vue";

defineProps<{
	workflowInput: WorkflowInput;
}>();

const isEditing = ref(false);
</script>
