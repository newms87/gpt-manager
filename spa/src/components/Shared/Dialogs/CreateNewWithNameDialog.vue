<template>
	<ConfirmDialog
		:title="title"
		:confirm-text="confirmText || title"
		@confirm="$emit('confirm', {name})"
		@close="$emit('close')"
	>
		<TextField v-model="name" label="Name" />
	</ConfirmDialog>
</template>
<script setup lang="ts">
import { ConfirmDialog, TextField } from "quasar-ui-danx";
import { ref } from "vue";

defineEmits(["confirm", "close"]);
defineProps<{
	title: string;
	confirmText?: string;
}>();
const name = ref("");
</script>
