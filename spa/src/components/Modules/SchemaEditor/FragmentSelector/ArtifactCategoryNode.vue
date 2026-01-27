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
			<div class="relative flex flex-col gap-1 px-3 py-2 rounded-t-lg nodrag nopan">
				<div class="flex items-center gap-2">
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

			<!-- Selection Badge -->
			<div v-if="selectionSummary" class="px-3 pb-2">
				<span class="text-[10px] bg-violet-800 text-violet-300 px-1.5 py-0.5 rounded">
					{{ selectionSummary }}
				</span>
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
			v-if="data.editEnabled && data.acd.deletable && !isDeleting"
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
				Edit {{ data.acd.label || data.acd.name }}
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
				</div>

				<!-- Toggle Options -->
				<div class="flex gap-6">
					<QToggle
						v-model="editForm.deletable"
						label="Deletable"
						color="violet"
						dark
					/>
					<QToggle
						v-model="editForm.is_editable"
						label="Editable"
						color="violet"
						dark
					/>
				</div>

				<!-- Save Button -->
				<div class="flex justify-end">
					<ActionButton
						label="Save"
						color="violet"
						@click="saveChanges"
					/>
				</div>
			</div>
		</InfoDialog>
	</div>
</template>

<script setup lang="ts">
import { ArtifactCategoryDefinition } from "@/types";
import { Handle, Position } from "@vue-flow/core";
import { FaSolidPen as EditIcon } from "danx-icon";
import { QSpinner, QToggle } from "quasar";
import { ActionButton, EditableDiv, InfoDialog, MarkdownEditor } from "quasar-ui-danx";
import { computed, reactive, ref, watch } from "vue";
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

const editForm = reactive({
	prompt: props.data.acd.prompt || "",
	deletable: props.data.acd.deletable ?? true,
	is_editable: props.data.acd.is_editable ?? true
});

// Reset form when dialog opens
watch(showEditDialog, (visible) => {
	if (visible) {
		editForm.prompt = props.data.acd.prompt || "";
		editForm.deletable = props.data.acd.deletable ?? true;
		editForm.is_editable = props.data.acd.is_editable ?? true;
	}
});

function saveChanges() {
	emit("edit", props.data.acd, {
		prompt: editForm.prompt,
		deletable: editForm.deletable,
		is_editable: editForm.is_editable
	});
	showEditDialog.value = false;
}

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

/**
 * Computes a human-readable summary of the fragment selector's selections.
 * Shows the count of selected models/properties if any selections exist.
 */
const selectionSummary = computed(() => {
	const selector = props.data.acd.fragment_selector;
	if (!selector?.children) return null;

	const count = countSelections(selector);
	if (count === 0) return null;

	return `${count} ${count === 1 ? "selection" : "selections"}`;
});

/**
 * Recursively counts the number of selections in a fragment selector.
 */
function countSelections(selector: { children?: Record<string, unknown> } | null): number {
	if (!selector?.children) return 0;

	let count = Object.keys(selector.children).length;
	for (const child of Object.values(selector.children)) {
		if (child && typeof child === "object" && "children" in child) {
			count += countSelections(child as { children?: Record<string, unknown> });
		}
	}
	return count;
}
</script>
