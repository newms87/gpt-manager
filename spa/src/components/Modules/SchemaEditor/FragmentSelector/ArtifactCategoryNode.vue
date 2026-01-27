<template>
	<div class="artifact-category-node min-w-48 relative group">
		<div
			class="border rounded-lg shadow-lg transition-colors"
			:class="isDeleting ? 'bg-red-900 border-red-600' : 'bg-violet-900 border-violet-600'"
		>
			<!-- Target Handle (always on left, connects from model's artifact button) -->
			<Handle
				id="target-left"
				type="target"
				:position="Position.Left"
				class="!bg-transparent !border-0 z-20"
			>
				<AcdIndicatorDot :direction="data.direction" />
			</Handle>

			<!-- Header -->
			<div class="relative flex flex-col gap-1 px-3 py-2 rounded-lg nodrag nopan">
				<div class="flex items-center gap-2">
					<!-- Artifact Icon -->
					<DocumentIcon class="w-3 h-3 text-violet-400 flex-shrink-0" />

					<!-- Label (editable) -->
					<EditableDiv
						:model-value="data.acd.label || data.acd.name"
						:readonly="!data.editEnabled"
						placeholder="Enter label..."
						color="violet-950"
						text-color="violet-200"
						class="font-semibold text-violet-200 text-sm flex-1"
						@update:model-value="onLabelUpdate"
					/>

					<!-- Edit button (visible on hover) -->
					<ActionButton
						v-if="data.editEnabled"
						:icon="EditIcon"
						color="violet"
						size="xs"
						tooltip="Edit Artifact Category"
						class="opacity-0 group-hover:opacity-100 transition-opacity"
						@click="showEditDialog = true"
					/>
				</div>

				<!-- Name (editable, shown if different from label) -->
				<EditableDiv
					v-if="data.acd.label && data.acd.name !== data.acd.label"
					:model-value="data.acd.name"
					:readonly="!data.editEnabled"
					placeholder="Enter name..."
					color="violet-950"
					text-color="violet-400"
					class="text-xs text-violet-400"
					@update:model-value="onNameUpdate"
				/>
			</div>
		</div>

		<!-- Loading overlay when deleting -->
		<div
			v-if="isDeleting"
			class="absolute inset-0 flex items-center justify-center bg-red-900/50 rounded-lg z-30"
		>
			<QSpinner color="white" size="24px" />
		</div>

		<!-- Delete button (hover outside right, centered vertically) -->
		<div
			v-if="data.editEnabled && !isDeleting"
			class="absolute -right-8 top-1/2 -translate-y-1/2 flex items-center opacity-0 group-hover:opacity-100 transition-opacity z-10"
		>
			<ActionButton
				type="trash"
				color="red"
				size="xs"
				tooltip="Delete Artifact Category"
				@click="onDelete"
			/>
		</div>

		<!-- Edit Dialog -->
		<InfoDialog
			v-if="showEditDialog"
			@close="showEditDialog = false"
		>
			<template #title>
				<div class="flex items-center justify-between w-full">
					<span>{{ data.acd.label }}</span>
					<SaveStateIndicator :saving="isSaving" :saved-at="data.acd.updated_at" />
				</div>
			</template>
			<div class="flex flex-col gap-4 w-[60vw]">
				<!-- Prompt Editor -->
				<div>
					<div class="text-sm font-semibold text-slate-300 mb-2">Prompt</div>
					<MarkdownEditor
						:model-value="editForm.prompt"
						min-height="200px"
						@update:model-value="editForm.prompt = $event"
					/>
					<div class="bg-gray-900 rounded-full px-3 py-1 inline-flex items-center gap-1.5 mt-3">
						<InfoIcon class="w-3 h-3 text-slate-400 flex-shrink-0" />
						<span class="text-xs text-slate-400">Instructions for generating this artifact from the selected data fields</span>
					</div>
				</div>

				<!-- Toggle Options -->
				<div class="bg-gray-800/50 border border-gray-700 rounded-lg p-4">
					<div class="text-sm font-semibold text-slate-300 mb-3">Options</div>
					<div class="flex flex-col gap-4">
						<div class="flex flex-col">
							<QToggle
								v-model="editForm.deletable"
								label="Deletable"
								color="violet"
								dark
							/>
							<div class="bg-gray-900 rounded-full px-3 py-1 flex items-center gap-1.5 w-fit">
								<InfoIcon class="w-3 h-3 text-slate-400 flex-shrink-0" />
								<span class="text-xs text-slate-400">Allow users to delete generated artifacts of this type</span>
							</div>
						</div>
						<div class="flex flex-col">
							<QToggle
								v-model="editForm.is_editable"
								label="Editable"
								color="violet"
								dark
							/>
							<div class="bg-gray-900 rounded-full px-3 py-1 flex items-center gap-1.5 w-fit">
								<InfoIcon class="w-3 h-3 text-slate-400 flex-shrink-0" />
								<span class="text-xs text-slate-400">Allow users to edit the content of generated artifacts</span>
							</div>
						</div>
					</div>
				</div>
			</div>
		</InfoDialog>
	</div>
</template>

<script setup lang="ts">
import { ArtifactCategoryDefinition } from "@/types";
import { useDebounceFn } from "@vueuse/core";
import { Handle, Position } from "@vue-flow/core";
import { FaSolidCircleInfo as InfoIcon, FaSolidFileLines as DocumentIcon, FaSolidPen as EditIcon } from "danx-icon";
import { QSpinner, QToggle } from "quasar";
import { ActionButton, EditableDiv, InfoDialog, MarkdownEditor, SaveStateIndicator } from "quasar-ui-danx";
import { reactive, ref, watch } from "vue";
import AcdIndicatorDot from "./AcdIndicatorDot.vue";
import { ArtifactCategoryNodeData } from "./types";

const props = defineProps<{
	data: ArtifactCategoryNodeData;
}>();

const emit = defineEmits<{
	edit: [acd: ArtifactCategoryDefinition, updates: Partial<ArtifactCategoryDefinition>];
	delete: [acd: ArtifactCategoryDefinition];
}>();

const showEditDialog = ref(false);
const isDeleting = ref(false);
const isSaving = ref(false);

const editForm = reactive({
	prompt: props.data.acd.prompt || "",
	deletable: props.data.acd.deletable ?? true,
	is_editable: props.data.acd.is_editable ?? true
});

// Debounced autosave function
const debouncedSave = useDebounceFn(() => {
	isSaving.value = true;
	emit("edit", props.data.acd, {
		prompt: editForm.prompt,
		deletable: editForm.deletable,
		is_editable: editForm.is_editable
	});
	// Reset saving state after a short delay (parent handles actual save)
	setTimeout(() => {
		isSaving.value = false;
	}, 500);
}, 500);

// Reset form when dialog opens
watch(showEditDialog, (visible) => {
	if (visible) {
		editForm.prompt = props.data.acd.prompt || "";
		editForm.deletable = props.data.acd.deletable ?? true;
		editForm.is_editable = props.data.acd.is_editable ?? true;
	}
});

// Watch form changes for autosave
watch(editForm, () => {
	if (showEditDialog.value) {
		debouncedSave();
	}
}, { deep: true });

function onLabelUpdate(label: string) {
	emit("edit", props.data.acd, { label });
}

function onNameUpdate(name: string) {
	emit("edit", props.data.acd, { name });
}

function onDelete() {
	isDeleting.value = true;
	emit("delete", props.data.acd);
}
</script>
