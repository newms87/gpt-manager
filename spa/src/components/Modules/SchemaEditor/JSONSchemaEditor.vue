<template>
	<div class="overflow-auto pb-8">
		<div class="flex items-center flex-nowrap space-x-2">
			<QBtn class="bg-sky-800" :disable="!canUndo">
				<UndoIcon class="w-4 cursor-pointer" @click="undo" />
				<QTooltip>Ctrl+Z</QTooltip>
			</QBtn>
			<QBtn class="bg-sky-800" :disable="!canRedo">
				<RedoIcon class="w-4 cursor-pointer" @click="redo" />
				<QTooltip>Ctrl+Y</QTooltip>
			</QBtn>
			<ShowHideButton v-model="isShowingRaw" class="bg-slate-700" :show-icon="RawCodeIcon" />
			<SaveStateIndicator :saving="saving" :saved-at="savedAt" class="ml-2" />
		</div>

		<QSeparator class="bg-slate-600 my-4" />

		<div>
			<SchemaObject
				v-if="!isShowingRaw"
				v-model="editableSchema"
				class="min-w-64"
			/>

			<MarkdownEditor
				v-else
				:model-value="editableSchema"
				sync-model-changes
				label=""
				format="yaml"
				@update:model-value="debounceUpdate"
			/>
		</div>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import SchemaObject from "@/components/Modules/SchemaEditor/SchemaObject";
import { JsonSchema } from "@/types";
import { useDebounceFn, useMagicKeys, useRefHistory, whenever } from "@vueuse/core";
import {
	FaSolidArrowRotateLeft as UndoIcon,
	FaSolidArrowRotateRight as RedoIcon,
	FaSolidCode as RawCodeIcon
} from "danx-icon";
import { SaveStateIndicator, ShowHideButton } from "quasar-ui-danx";
import { ref, watch } from "vue";

defineProps<{
	savedAt?: string;
	saving: boolean;
}>();
const schema = defineModel<JsonSchema>();
const editableSchema = ref(schema.value);
const isShowingRaw = ref(false);
const debounceUpdate = useDebounceFn((input: JsonSchema) => {
	schema.value = input;
}, 1000);

watch(() => editableSchema.value, debounceUpdate);
const { undo, redo, canUndo, canRedo, history } = useRefHistory(editableSchema, { deep: true, capacity: 100 });
const magicKeys = useMagicKeys();
whenever(magicKeys.ctrl_z, () => {
	canUndo.value && undo();
});
whenever(magicKeys.ctrl_y, () => {
	canRedo.value && redo();
});
whenever(magicKeys.ctrl_shift_z, () => {
	canRedo.value && redo();
});
</script>
