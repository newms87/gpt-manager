<template>
	<div class="h-full p-6 overflow-y-auto">
		<SelectOrCreateField
			v-model:editing="isEditingSchema"
			:selected="dxPromptSchema.activeItem"
			show-edit
			:can-edit="!!dxPromptSchema.activeItem.value"
			:options="dxPromptSchema.pagedItems.value?.data || []"
			:loading="createAction.isApplying"
			select-by-object
			option-label="name"
			@create="onCreate"
			@update:selected="dxPromptSchema.activeItem.value = $event"
		/>

		<div v-if="isEditingSchema">
			{{ dxPromptSchema.activeItem.value }}
			<MarkdownEditor
				:model-value="dxPromptSchema.activeItem.value?.schema"
				class="mt-4"
				label=""
				@update:model-value="updateDebouncedSchemaAction.trigger(dxPromptSchema.activeItem.value, { schema: $event })"
			/>
		</div>

		<div>
			<QSkeleton
				v-for="i in 3"
				:key="i"
				class="mt-4"
				height="5em"
			/>
		</div>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MardownEditor/MarkdownEditor";
import { getAction } from "@/components/Modules/Prompts/Schemas/promptSchemaActions";
import { dxPromptSchema } from "@/components/Modules/Prompts/Schemas/promptSchemaControls";
import { SelectOrCreateField } from "quasar-ui-danx";
import { onMounted, ref } from "vue";

onMounted(dxPromptSchema.initialize);

const createAction = getAction("create");
const updateDebouncedSchemaAction = getAction("update-debounced");
const isEditingSchema = ref(false);
async function onCreate() {
	await createAction.trigger(dxPromptSchema.activeItem.value);
}
</script>
