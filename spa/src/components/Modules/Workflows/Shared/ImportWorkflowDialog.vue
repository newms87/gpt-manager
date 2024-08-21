<template>
	<ConfirmDialog title="Import Workflow from JSON Export" @confirm="$emit('confirm', { import_file_id: file.id })">
		<SingleFileField v-model="file" label="Select JSON File" accept=".json" />
	</ConfirmDialog>
</template>

<script setup lang="ts">
import { ConfirmDialog, SingleFileField, UploadedFile } from "quasar-ui-danx";
import { ref } from "vue";

defineEmits(["confirm"]);
const file = ref<UploadedFile | null>(null);
</script>
