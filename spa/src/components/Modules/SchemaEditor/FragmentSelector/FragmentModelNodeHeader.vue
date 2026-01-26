<template>
	<div class="relative flex items-center gap-2 bg-slate-700 px-3 py-2 rounded-t-lg group nodrag nopan">
		<!-- DELETE BUTTON: Container extends from header edge to button, no gap -->
		<div
			v-if="editEnabled && !isRoot"
			class="absolute right-0 top-0 bottom-0 flex items-center pl-4 pr-2 -mr-10 opacity-0 group-hover:opacity-100 transition-opacity z-10"
		>
			<ActionButton
				type="trash"
				color="red"
				size="xs"
				@click="emit('remove-model')"
			/>
		</div>

		<!-- LEFT ELEMENT: Checkbox (select) / Spacer (edit & readonly) -->
		<div class="w-5 flex-shrink-0 flex items-center justify-center">
			<Transition name="fade" mode="out-in">
				<QCheckbox
					v-if="selectionEnabled"
					key="checkbox"
					:model-value="checkboxValue"
					:indeterminate-value="null"
					size="sm"
					color="sky"
					dark
					@update:model-value="emit('toggle-all')"
				/>
				<div v-else key="spacer" class="w-4 h-4" />
			</Transition>
		</div>

		<!-- TITLE: EditableDiv - readonly when not editing -->
		<EditableDiv
			ref="titleInputRef"
			:model-value="title"
			:readonly="!editEnabled"
			placeholder="Enter name..."
			color="slate-600"
			class="font-bold text-sm text-slate-100 flex-1"
			@update:model-value="onTitleUpdate"
		/>

		<!-- DESCRIPTION BUTTON -->
		<ShowHideButton
			v-model="modelDescriptionVisible"
			:show-icon="DescriptionIcon"
			:hide-icon="DescriptionIcon"
			size="sm"
			class="flex-shrink-0"
			:class="description ? 'text-sky-600' : 'text-slate-500'"
		/>

		<InfoDialog
			v-if="modelDescriptionVisible"
			@close="modelDescriptionVisible = false"
		>
			<template #title>
				<span>{{ title }}</span>
			</template>
			<MarkdownEditor
				class="w-[70vw]"
				:model-value="description || ''"
				readonly
				hide-footer
				min-height="60px"
			/>
		</InfoDialog>
	</div>
</template>

<script setup lang="ts">
import { FaSolidFileLines as DescriptionIcon } from "danx-icon";
import { QCheckbox } from "quasar";
import { ActionButton, EditableDiv, InfoDialog, MarkdownEditor, ShowHideButton } from "quasar-ui-danx";
import { ref, Transition, watch } from "vue";

const props = defineProps<{
	title: string;
	description?: string;
	editEnabled: boolean;
	selectionEnabled: boolean;
	isRoot: boolean;
	checkboxValue: boolean | null;
	shouldFocus?: boolean;
}>();

const emit = defineEmits<{
	"toggle-all": [];
	"update-model": [title: string];
	"remove-model": [];
}>();

const modelDescriptionVisible = ref(false);
const titleInputRef = ref<InstanceType<typeof EditableDiv> | null>(null);

// Watch for focus trigger from parent (when new model is created)
watch(() => props.shouldFocus, (shouldFocus) => {
	if (shouldFocus && titleInputRef.value) {
		titleInputRef.value.focus(true); // Select all text when focusing
	}
});

function onTitleUpdate(title: string) {
	emit("update-model", title);
}
</script>

