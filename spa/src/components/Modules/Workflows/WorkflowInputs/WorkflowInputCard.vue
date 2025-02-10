<template>
	<div>
		<div class="flex items-center">
			<div class="flex-grow flex items-center flex-nowrap space-x-4 h-20">
				<ShowHideButton v-if="!readonly" v-model="isEditing" :show-icon="EditIcon" class="bg-slate-700" />
				<QBtn v-if="readonly" class="rounded-full bg-sky-800 text-sky-200 px-4 py-2" @click="$emit('select')">
					{{ workflowInput.name }}
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
			</div>
			<slot name="actions" />
			<ActionButton
				v-if="removable"
				type="trash"
				color="red"
				class="bg-red-200"
				:saving="removing"
				@click="$emit('remove')"
			/>
		</div>
		<div v-if="editableTeamObjects" class="mb-4 flex items-center flex-nowrap space-x-4">
			<SelectField
				select-class="dx-select-field-dense"
				placeholder="(Select Type)"
				:options="dxWorkflowInput.getFieldOptions('teamObjectTypes')"
				:model-value="workflowInput.team_object_type"
				@update:model-value="onUpdateTeamObjectType"
			/>
			<SelectionMenuField
				v-if="workflowInput.team_object_type"
				:selected="workflowInput.teamObject"
				selectable
				clearable
				:placeholder="`(Select ${workflowInput.team_object_type})`"
				:select-icon="TeamObjectIcon"
				select-class="bg-emerald-900 text-cyan-400"
				label-class="text-slate-300"
				:options="workflowInput.availableTeamObjects || []"
				:loading="updateAction.isApplying"
				@update:selected="teamObject => updateAction.trigger(workflowInput, { team_object_id: teamObject?.id || null })"
			/>
		</div>
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
import { ActionButton } from "@/components/Shared";
import { WorkflowInput } from "@/types";
import { FaSolidIndustry as TeamObjectIcon, FaSolidPencil as EditIcon } from "danx-icon";
import {
	EditableDiv,
	MultiFileField,
	SaveStateIndicator,
	SelectField,
	SelectionMenuField,
	ShowHideButton
} from "quasar-ui-danx";
import { onMounted, ref } from "vue";

defineEmits(["select", "remove"]);
const props = defineProps<{
	workflowInput: WorkflowInput;
	readonly?: boolean;
	removable?: boolean;
	removing?: boolean;
	editableTeamObjects?: boolean;
}>();

onMounted(() => {
	dxWorkflowInput.loadFieldOptions();
	loadAvailableTeamObjects();
});
const updateAction = dxWorkflowInput.getAction("update");
const debouncedUpdateAction = dxWorkflowInput.getAction("update", { debounce: 500 });
const isEditing = ref(false);

async function loadAvailableTeamObjects() {
	if (props.workflowInput.team_object_type) {
		await dxWorkflowInput.routes.detailsAndStore(props.workflowInput, { availableTeamObjects: true });
	}
}

function onUpdateTeamObjectType(team_object_type) {
	updateAction.trigger(props.workflowInput, { team_object_type, team_object_id: null });
	loadAvailableTeamObjects();
}
</script>
