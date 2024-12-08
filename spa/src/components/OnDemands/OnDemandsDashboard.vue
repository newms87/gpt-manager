<template>
	<div class="h-full p-6 overflow-hidden">
		<SchemaEditorToolbox v-model:editing="isEditingSchema" />

		<TeamObjectsList v-if="activeSchema && !isEditingSchema" :prompt-schema="activeSchema" />
	</div>
</template>
<script setup lang="ts">
import { dxPromptSchema } from "@/components/Modules/Prompts/Schemas";
import SchemaEditorToolbox from "@/components/Modules/SchemaEditor/SchemaEditorToolbox";
import TeamObjectsList from "@/components/Modules/TeamObjects/TeamObjectsList";
import { computed, ref } from "vue";

const isEditingSchema = ref(false);
const activeSchema = computed(() => dxPromptSchema.activeItem.value);
</script>
