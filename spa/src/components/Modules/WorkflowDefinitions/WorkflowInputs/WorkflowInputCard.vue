<template>
	<div>
		<div class="flex-x space-x-2">
			<div class="flex-grow flex-x space-x-4">
				<ShowHideButton v-if="!readonly" v-model="isEditing" :show-icon="EditIcon" class="bg-slate-700" />
				<QBtn v-if="readonly && !isEditing" class="rounded-full bg-sky-800 text-sky-200 px-4 py-2">
					{{ workflowInput.name }}
				</QBtn>
				<div v-else class="rounded-full bg-sky-800 text-sky-200 px-4 py-2">
					<EditableDiv
						:readonly="readonly && !isEditing"
						color="sky-800"
						:model-value="workflowInput.name"
						placeholder="Enter Name..."
						@update:model-value="name => updateAction.trigger(workflowInput, { name })"
					/>
				</div>
				<EditableDiv
					:readonly="readonly && !isEditing"
					color="slate-700"
					:model-value="workflowInput.description"
					placeholder="Enter Description..."
					@update:model-value="description => updateAction.trigger(workflowInput, { description })"
				/>
			</div>
			<slot name="actions" />
			<ActionButton
				v-if="selectable"
				type="confirm"
				label="Select"
				color="green"
				class="py-1.5"
				@click="$emit('select')"
			/>
			<ActionButton
				v-if="removable"
				type="trash"
				color="red"
				class="bg-red-200"
				:saving="removing"
				confirm
				@always="$emit('remove')"
			/>
		</div>
		<div v-if="editableTeamObjects" class="mt-4 flex-x space-x-4">
			<SelectField
				:loading="isLoadingTeamObjects"
				placeholder="(Select Type)"
				:options="dxWorkflowInput.getFieldOptions('teamObjectTypes')"
				:model-value="workflowInput.team_object_type"
				@update:model-value="onUpdateTeamObjectType"
			/>
			<SelectionMenuField
				v-if="workflowInput.team_object_type"
				:selected="selectedTeamObject"
				selectable
				clearable
				:placeholder="`(Select ${workflowInput.team_object_type})`"
				:select-icon="TeamObjectIcon"
				label-class="text-slate-300 text-lg"
				size="lg"
				:options="availableTeamObjects"
				:loading="isLoadingTeamObjects"
				@update:selected="teamObject => updateAction.trigger(workflowInput, { team_object_id: teamObject?.id || null })"
			/>
		</div>
		<MultiFileField
			v-if="!readonly || isEditing || workflowInput.files?.length > 0 || !!workflowInput.thumb"
			:readonly="readonly && !isEditing"
			:disabled="!isEditing"
			:model-value="workflowInput.files || (workflowInput.thumb ? [workflowInput.thumb] : [])"
			:width="70"
			:height="60"
			add-icon-class="w-5"
			show-transcodes
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
import { dxWorkflowInput } from "@/components/Modules/WorkflowDefinitions/WorkflowInputs/config";
import {
	availableTeamObjectsByType,
	isLoadingAvailableTeamObjects,
	loadAvailableTeamObjectsByType
} from "@/components/Modules/WorkflowDefinitions/WorkflowInputs/teamObjectStore";
import { WorkflowInput } from "@/types";
import { FaSolidIndustry as TeamObjectIcon, FaSolidPencil as EditIcon } from "danx-icon";
import {
	ActionButton,
	EditableDiv,
	MultiFileField,
	SaveStateIndicator,
	SelectField,
	SelectionMenuField,
	ShowHideButton
} from "quasar-ui-danx";
import { computed, onMounted, watch } from "vue";

defineEmits(["select", "remove"]);
const props = defineProps<{
	workflowInput: WorkflowInput;
	readonly?: boolean;
	selectable?: boolean;
	removable?: boolean;
	removing?: boolean;
	editableTeamObjects?: boolean;
}>();

onMounted(() => loadAvailableTeamObjectsByType(props.workflowInput.team_object_type));
watch(() => props.workflowInput.team_object_type, () => loadAvailableTeamObjectsByType(props.workflowInput.team_object_type));

// State
const isEditing = defineModel<boolean>("editing");
const isLoadingTeamObjects = computed(() => updateAction.isApplying || isLoadingAvailableTeamObjects.value[props.workflowInput.team_object_type]);
const availableTeamObjects = computed(() => availableTeamObjectsByType.value[props.workflowInput.team_object_type] || []);
const selectedTeamObject = computed(() => availableTeamObjects.value.find(to => to.id === props.workflowInput.team_object_id));

// Actions
const updateAction = dxWorkflowInput.getAction("update");
const debouncedUpdateAction = dxWorkflowInput.getAction("update", { debounce: 500 });

// Methods
function onUpdateTeamObjectType(team_object_type) {
	updateAction.trigger(props.workflowInput, { team_object_type, team_object_id: null });
}
</script>
