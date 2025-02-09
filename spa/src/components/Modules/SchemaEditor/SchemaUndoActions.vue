<template>
	<div class="flex items-center flex-nowrap space-x-2">
		<QBtn class="bg-sky-800" :disable="!canUndo">
			<UndoIcon class="w-4 cursor-pointer" @click="undo" />
			<QTooltip>Ctrl+Z</QTooltip>
		</QBtn>
		<QBtn class="bg-sky-800" :disable="!canRedo">
			<RedoIcon class="w-4 cursor-pointer" @click="redo" />
			<QTooltip>Ctrl+Y</QTooltip>
		</QBtn>
	</div>
</template>

<script setup lang="ts">
import { SchemaDefinition } from "@/types";
import { useMagicKeys, useRefHistory, whenever } from "@vueuse/core";
import { FaSolidArrowRotateLeft as UndoIcon, FaSolidArrowRotateRight as RedoIcon } from "danx-icon";
import { ref, watch } from "vue";

const schema = defineModel<{ type: SchemaDefinition }>();
const editableSchema = ref(schema.value);
watch(() => schema.value, () => {
	// Don't add the same schema to the history
	if (JSON.stringify(editableSchema.value) !== JSON.stringify(schema.value)) {
		editableSchema.value = schema.value;
	}
});
watch(() => editableSchema.value, () => {
	if (JSON.stringify(editableSchema.value) !== JSON.stringify(schema.value)) {
		schema.value = editableSchema.value;
	}
});

const { undo, redo, canUndo, canRedo } = useRefHistory(editableSchema, { deep: true, capacity: 100 });

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
