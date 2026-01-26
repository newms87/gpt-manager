<template>
	<div class="flex items-center group relative">
		<!-- Main row container -->
		<div
			class="flex items-center gap-2 px-3 py-1 transition-colors flex-1 nodrag nopan"
			:class="isSelected ? 'bg-sky-900/30' : 'hover:bg-slate-700/50'"
		>
			<!-- LEFT ELEMENT: Checkbox (select) / Drag Handle (edit) / Spacer (readonly) -->
			<div class="w-5 flex-shrink-0 flex items-center justify-center">
				<Transition name="fade" mode="out-in">
					<QCheckbox
						v-if="selectionActive"
						key="checkbox"
						:model-value="isSelected"
						size="sm"
						color="sky"
						dark
						dense
						@update:model-value="$emit('toggle')"
					/>
					<SvgImg
						v-else-if="editActive"
						key="drag-handle"
						:svg="DragHandleIcon"
						class="w-3 h-3 text-slate-500 cursor-grab"
						alt="drag-handle"
					/>
					<div v-else key="spacer" class="w-3 h-3" />
				</Transition>
			</div>

			<!-- TYPE: Menu (interactive in edit mode, readonly otherwise) -->
			<SchemaPropertyTypeMenu
				:property="property"
				:readonly="!editActive"
				@update="onTypeUpdate"
			/>

			<!-- NAME: EditableDiv - readonly when not editing -->
			<EditableDiv
				:model-value="property.title || name"
				:readonly="!editActive"
				placeholder="Enter name..."
				color="slate-600"
				class="flex-1 text-sm"
				@update:model-value="onNameUpdate"
			/>

			<!-- DESCRIPTION BUTTON (always when showDescription) -->
			<ShowHideButton
				v-if="showDescription"
				v-model="descriptionVisible"
				:show-icon="DescriptionIcon"
				:hide-icon="DescriptionIcon"
				size="sm"
				class="flex-shrink-0"
				:class="property.description ? 'text-sky-600' : 'text-slate-500'"
				@click.stop
			/>
		</div>

		<!-- DELETE BUTTON: Container extends from row edge to button, no gap -->
		<div
			v-if="editActive"
			class="absolute right-0 top-0 bottom-0 flex items-center pl-4 pr-2 -mr-10 opacity-0 group-hover:opacity-100 transition-opacity"
		>
			<ActionButton
				type="trash"
				color="red"
				size="xs"
				@click="$emit('remove')"
			/>
		</div>

		<!-- Description Dialog -->
		<InfoDialog
			v-if="descriptionVisible"
			@close="descriptionVisible = false"
		>
			<template #title>
				<span>{{ property.title || name }}</span>
			</template>
			<MarkdownEditor
				class="w-[70vw]"
				:model-value="property.description || ''"
				readonly
				hide-footer
				min-height="60px"
			/>
		</InfoDialog>
	</div>
</template>

<script setup lang="ts">
import SchemaPropertyTypeMenu from "@/components/Modules/SchemaEditor/SchemaPropertyTypeMenu.vue";
import { JsonSchema, JsonSchemaType } from "@/types";
import { FaSolidFileLines as DescriptionIcon } from "danx-icon";
import { QCheckbox } from "quasar";
import { ActionButton, DragHandleIcon, EditableDiv, InfoDialog, MarkdownEditor, ShowHideButton, SvgImg } from "quasar-ui-danx";
import { ref, Transition } from "vue";

const props = defineProps<{
	name: string;
	property: JsonSchema;
	editActive: boolean;
	selectionActive: boolean;
	isSelected: boolean;
	showDescription: boolean;
}>();

const emit = defineEmits<{
	toggle: [];
	"update-name": [newName: string];
	"update-type": [typeUpdate: { type: JsonSchemaType; format?: string }];
	remove: [];
}>();

const descriptionVisible = ref(false);

function onNameUpdate(newName: string) {
	emit("update-name", newName);
}

function onTypeUpdate(typeUpdate: { type: JsonSchemaType; format?: string }) {
	emit("update-type", typeUpdate);
}
</script>

