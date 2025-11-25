<template>
	<div class="flex items-center gap-2 mr-3">
		<ActionButton
			:icon="MessagesIcon"
			:color="allMessagesExpanded ? 'sky' : 'slate'"
			size="sm"
			:tooltip="allMessagesExpanded ? 'Collapse all messages' : 'Expand all messages'"
			@click="$emit('toggle-messages')"
		/>
		<ActionButton
			:icon="FilesIcon"
			:color="allFilesExpanded ? 'sky' : 'slate'"
			size="sm"
			:tooltip="allFilesExpanded ? 'Collapse all files' : 'Expand all files'"
			@click="$emit('toggle-files')"
		/>
	</div>
</template>

<script setup lang="ts">
import { ActionButton } from "quasar-ui-danx";
import {
	FaSolidMessage as MessagesIcon,
	FaSolidImage as FilesIcon
} from "danx-icon";

defineProps<{
	allMessagesExpanded: boolean;
	allFilesExpanded: boolean;
}>();

defineEmits(["toggle-messages", "toggle-files"]);
</script>
